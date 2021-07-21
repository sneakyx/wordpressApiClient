<?php

namespace WordpressApiClient;


class WordpressApiClient
{

    /**
     * @var string
     */
    private $basicUrl;

    /**
     * @var false|resource
     */
    private $curlHandler;

    /**
     * @var null|array
     */
    private $sortedCategories = null;

    /**
     * @var null|array
     */
    private $restrictedRootCategories = null;

    /**
     * WordpressApiClient constructor.
     *
     * @param       $username                  // wordpress username
     * @param       $password                  // wordpress password
     * @param       $basicUrl                  // basic URL with or without trailing slash
     * @param null  $restrictedRootCategories  // if you want to restrict to one or more root categories, provide IDs or slugs
     */
    public function __construct($username, $password, $basicUrl, $restrictedRootCategories = null)
    {
        // correct basicurl-> add trailing slash
        if (substr($basicUrl, -1) !== '/') {
            $basicUrl .= "/";
        }
        $this->basicUrl = $basicUrl;

        // urlencode username and password
        $password = urlencode($password);
        $username = urlencode($username);

        // login
        $this->curlHandler = curl_init("{$basicUrl}wp-login.php");
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curlHandler, CURLOPT_COOKIESESSION, true);
        curl_setopt($this->curlHandler, CURLOPT_HEADER, 1);
        curl_setopt($this->curlHandler, CURLOPT_POST, 1);
        curl_setopt($this->curlHandler, CURLOPT_POSTFIELDS,
            "log={$username}&pwd={$password}&testcookie=1");
        $result = curl_exec($this->curlHandler);

        // get cookie
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
        $cookies = $matches[1];

        // set cookies for further requests
        curl_setopt($this->curlHandler, CURLOPT_COOKIE, implode('; ', $cookies));

        // set restrictedRootCategories
        $this->restrictedRootCategories = $restrictedRootCategories;
    }

    /**
     * simple return api data by get call
     *
     * @param string  $path
     * @param bool    $returnAsArray
     *
     * @return bool|mixed|string
     */
    public function getApiData($path = 'posts', $returnAsArray = true)
    {
        curl_setopt_array($this->curlHandler, [
            CURLOPT_URL => "{$this->basicUrl}wp-json/wp/v2/{$path}",
            CURLOPT_POST => 0,
            CURLOPT_HEADER => 0,
        ]);
        $result = curl_exec($this->curlHandler);
        if ($returnAsArray === true) {
            // return as array
            return json_decode($result, true);
        }
        // return as json
        return $result;
    }

    /**
     * getter method for ordered categories with parent, children, successors and ancestors
     * @return null
     */
    public function getOrderedCategories()
    {
        // lazy loading, do api request only when there is nothing loaded yet
        if ($this->sortedCategories === null) {
            // if not loaded yet, load categories
            $sortedCategories = [];

            // get data from wordpress api
            $unsortedCategories = $this->getApiData('categories?per_page=100&orderby=id&_fields=id,name,slug,parent,meta');
            // set key from id
            foreach ($unsortedCategories as &$category) {
                $sortedCategories[$category['id']] = $category;
                if ($category['parent'] === 0) {
                    // if it is a main category, remove reference to non-existing category 0
                    $sortedCategories[$category['id']]['parent'] = null;
                }
            }

            // create ancerstors path
            foreach ($sortedCategories as &$sortedCategory) {
                $sortedCategory['ancestors'] = $this->recursiveFindPathAndAncestors($sortedCategories, $sortedCategory['id']);
                $sortedCategory['depth'] = count($sortedCategory['ancestors']);
            }
            // remove reference - see https://www.php.net/manual/en/control-structures.foreach.php
            unset($sortedCategory);

            // add ancestors and (direct) children to categories
            foreach ($sortedCategories as $sortedCategory) {
                // not neccessary, main categories doesn't have ancestors/ parents
                if ($sortedCategory['depth'] > 0) {
                    // childrens are first-level children only, no grandchildren or great-(great-...)-children
                    $sortedCategories[$sortedCategory['parent']]['children'][] = $sortedCategory['id'];
                    foreach ($sortedCategory['ancestors'] as $ancestorId) {
                        // add to succesor list of all successors
                        $sortedCategories[$ancestorId]['successors'][] = $sortedCategory['id'];
                    }
                }
            }

            // filter restrictedRootCategories if neccessary
            if (empty($this->restrictedRootCategories) === false) {
                $filteredCategories = [];
                $categoriesSlugs = array_column($sortedCategories, 'slug', 'id');
                foreach ($this->restrictedRootCategories as $restrictedRootCategory) {
                    // correct root categories
                    if (is_int($restrictedRootCategory) === false) {
                        $restrictedRootCategory = array_search($restrictedRootCategory, $categoriesSlugs);
                    }
                    // check if slug was found
                    if (empty($restrictedRootCategory) === false) {
                        // add this 'root' category
                        $filteredCategories[$restrictedRootCategory] = $sortedCategories[$restrictedRootCategory];
                        foreach ($sortedCategories[$restrictedRootCategory]['successors'] as $successor) {
                            // add successor
                            $filteredCategories[$successor] = $sortedCategories[$successor];
                        }
                    }
                }
                // don't use full array, just the filtered categories
                $this->sortedCategories = $filteredCategories;
            } else {
                $this->sortedCategories = $sortedCategories;
            }
        }
        return $this->sortedCategories;
    }

    /**
     * recursive function to add ancestors info to list of categories- this is also necessary for generating succesors info later
     *
     * @param $sortedCategories
     * @param $id
     *
     * @return array
     */
    private function recursiveFindPathAndAncestors($sortedCategories, $id)
    {
        if (empty($sortedCategories[$id]['ancestors']) === false) {
            return $sortedCategories[$id]['ancestors'];
        } else {

            if ($sortedCategories[$id]['parent'] === null) {
                return [];
            } else {
                $recursiveAnswer = $this->recursiveFindPathAndAncestors($sortedCategories, $sortedCategories[$id]['parent']);
                $recursiveAnswer[] = $sortedCategories[$id]['parent'];
                return $recursiveAnswer;
            }
        }
    }

    /**
     * get all posts
     *
     * @param array|null  $categories
     * @param bool        $withSuccessorsCategories
     * @param array|null  $parameters  // use parameters as array: ['parameter1'=>'value1']
     *
     * @return bool|mixed|string
     */
    public function getPosts(array $categories = null, $withSuccessorsCategories = true, array $parameters = null)
    {
        // initalize search string
        $searchString = "";

        // when there is a restriction, ensure there is a lazy load of categories
        if (empty($categories) === true) {
            $categories = $this->restrictedRootCategories;
        }
        // filter by category if necessary
        if (empty($categories) === false || empty($this->restrictedRootCategories) === false) {
            $orderedCategories = $this->getOrderedCategories();
            $categoriesSlugs = array_column($orderedCategories, 'slug', 'id');
            foreach ($categories as $category) {
                // if slug was used instead of category id, find category id first
                if (is_int($category) === false) {
                    $category = array_search($category, $categoriesSlugs);
                }
                // add this category - if not empty
                if (empty($orderedCategories[$category]) === false) {
                    $categoriesForSearch[] = $category;
                    if ($withSuccessorsCategories === true) {
                        // add all successors for this category - if there are some
                        if (empty($orderedCategories[$category]['successors']) === false) {
                            $categoriesForSearch = array_merge($categoriesForSearch, $orderedCategories[$category]['successors']);
                        }
                    }
                }
            }

            if (empty($categoriesForSearch) === true) {
                // searched categories not found
                return [];
            }
            // filter double values
            $categoriesForSearch = array_unique($categoriesForSearch);
            // generate full string for search / filter
            $searchString = "categories[]=" . implode('&categories[]=', $categoriesForSearch);

            // we cannot move this if request out of this if request
            if (empty($parameters) === false) {
                // the string has to be set only
                $searchString .= "&";
            }
        }

        // add other parameters to search string
        if (empty($parameters) === false) {
            $searchString .= http_build_query($parameters, '', '&');
        }
        // get and return the api data
        return $this->getApiData("posts?{$searchString}");
    }

    /**
     * get single post
     *
     * @param int         $id                             // id of post to get
     * @param array|null  $parameters                     // use parameters as array: ['parameter1'=>'value1']
     * @param bool        $checkIfInRestrictedCategories  // check if post is in restricted categories = if we are allowed to see this post
     *
     * @return bool|mixed|string
     */
    public function getPost(int $id, array $parameters = null, bool $checkIfInRestrictedCategories = true)
    {
        $parameterString = '';
        // concat parameters
        if (empty($parameters) === false) {
            $parameterString .= http_build_query($parameters, '', '&');
        }
        // get the api data

        $post = $this->getApiData("posts/{$id}?{$parameterString}");
        if ($checkIfInRestrictedCategories === true && empty($this->restrictedRootCategories) === false) {
            foreach ($this->restrictedRootCategories as $restrictedRootCategory) {
                if ($this->isPostInCategory($post, $restrictedRootCategory, true) === true) {
                    return $post;
                }
            }
            // it is not in restricted categories
            return false;
        }
        // return post here, because restricted categories shouldn't be checked
        return $post;
    }

    /**
     * @param       $post
     * @param       $category
     * @param bool  $alsoSubcategories
     *
     * @return bool
     */
    protected function isPostInCategory($post, $category, $alsoSubcategories = true)
    {
        // empty cases
        if (empty($post) === true || empty($post['categories']) === true) {
            return false;
        }
        // get all categories to check if there is a subcategory
        $orderedCategories = $this->getOrderedCategories();
        $categoriesSlugs = array_column($orderedCategories, 'slug', 'id');
        // check every category in post
        foreach ($post['categories'] as $postCategory) {
            if (is_int($category) === false) {
                $category = array_search($category, $categoriesSlugs);
            }
            if (
                $postCategory === $category || // post is in exact this category
                (in_array($postCategory, $this->getOrderedCategories()[$category]['successors']) && $alsoSubcategories === true) // post is in subcategory
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param       $filename
     * @param bool  $caseSensitiv
     *
     * @return array
     */
    public function getMediaByFilename($filename, $caseSensitiv = true)
    {
        $mediafiles = [];
        $page = 1;

        do {
            $media = $this->getApiData("media?page=$page");
            foreach ($media as $file) {
                if (array_key_exists('source_url', $file)) {
                    if (($caseSensitiv === true && strpos($file['source_url'], $filename) !== false) || // case sensitiv search
                        ($caseSensitiv === false && stripos($file['source_url'], $filename) !== false)) { // case insensitiv search
                        $mediafiles[] = $file;
                    }
                }
            }
            // increase page for next occurences - thanks to Timo
            $page++;
        } while (empty($media) === false);

        return $mediafiles;
    }

}
