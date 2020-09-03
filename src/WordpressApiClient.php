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
            CURLOPT_URL => "{$this->basicUrl}/wp-json/wp/v2/{$path}",
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

        }

        return $this->sortedCategories;
    }

    /**
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
}
