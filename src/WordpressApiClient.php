<?php

namespace WordpressApiClient;


class WordpressApiClient{

    /**
     * @var string
     */
    private $basicUrl;

    /**
     * @var false|resource
     */
    private $curlHandler;

    /**
     * WordpressApiClient constructor.
     * @param $username
     * @param $password
     */
    public function __construct($username, $password, $basicUrl)
    {
        // correct basicurl-> add trailing slash
        if (substr($basicUrl,-1)!=='/'){
            $basicUrl.="/";
        }
        $this->basicUrl=$basicUrl;

        // urlencode username and password
        $password=urlencode($password);
        $username=urlencode($username);

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
     * @param string $path
     * @return bool|string
     */
    public function getApiData($path='posts')
    {
        curl_setopt_array($this->curlHandler, array(
            CURLOPT_URL => "{$this->basicUrl}/wp-json/wp/v2/{$path}",
            CURLOPT_POST => 0,
            CURLOPT_HEADER => 0,
        ));
        $result = curl_exec($this->curlHandler);
        return $result;
    }

}
