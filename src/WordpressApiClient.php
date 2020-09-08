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

    private $sortedCategories = null;

    /**
     * WordpressApiClient constructor.
     * @param $username
     * @param $password
     */
    public function __construct($username, $password, $basicUrl)
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
    }

    /**
     * simple return api data by get call
     *
     * @param string $path
     * @param bool $returnAsArray
     * @return bool|mixed|string
     */
    public function getApiData($path = 'posts', $returnAsArray = true)
    {
        curl_setopt_array($this->curlHandler, array(
            CURLOPT_URL => "{$this->basicUrl}wp-json/wp/v2/{$path}",
            CURLOPT_POST => 0,
            CURLOPT_HEADER => 0,
        ));
        $result = curl_exec($this->curlHandler);
        if ($returnAsArray === true) {
            // return as array
            return json_decode($result, true);
        }
        // return as json
        return $result;
    }

    /**
     * getter method for orderd categories with parent, children, successors and ancestors
     * @return null
     */
    public function getOrderedCategories()
    {
        // lazy loading, do api request only when there is nothing loaded yet
        if ($this->sortedCategories === null) {
            // if not loaded yet, load categories
            $sortedCategories = array();

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
            $this->sortedCategories = $sortedCategories;

        }
        return $this->sortedCategories;
    }

    /**
     * recursive function to add ancestors info to list of categories- this is also necessary for generating succesors info later
     * @param $sortedCategories
     * @param $id
     * @return array
     */
    private function recursiveFindPathAndAncestors($sortedCategories, $id)
    {
        if (empty($sortedCategories[$id]['ancestors']) === false) {
            return $sortedCategories[$id]['ancestors'];
        } else {

            if ($sortedCategories[$id]['parent'] === null) {
                return array();
            } else {
                $recursiveAnswer = $this->recursiveFindPathAndAncestors($sortedCategories, $sortedCategories[$id]['parent']);
                $recursiveAnswer[] = $sortedCategories[$id]['parent'];
                return $recursiveAnswer;
            }
        }
    }

    /**
     * @param array|null $categories
     * @param bool $withSuccessorsCategories
     * @param array|null $parameters // use parameters as array: ['parameter1'=>'value1']
     * @return bool|mixed|string
     */
    public function getPosts(array $categories = null, $withSuccessorsCategories = true, array $parameters = null)
    {
        // initalize search string
        $searchString = "";
        if (empty($categories) === false) {
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
}
