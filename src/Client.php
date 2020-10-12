<?php

namespace ScraperAPI;

use ScraperAPI\Exceptions\ConnectException;
use Unirest;

class Client
{
    const ACCOUNT_URL = 'https://api.scraperapi.com/account';

    const QUERY_URL = 'http://api.scraperapi.com/?%s';

    public $api_key;

    public function __construct($key)
    {
        $this->api_key = $key;
    }

    public function get($url, $options = [])
    {
        return $this->scrape($url, Unirest\Method::GET, $options, null);
    }

    public function post($url, $options = [], $body = null)
    {
        return $this->scrape($url, Unirest\Method::POST, $options, $body);
    }

    public function put($url,  $options = [], $body = null)
    {
        return $this->scrape($url, Unirest\Method::PUT, $options, $body);
    }

    public function account()
    {
        $response = Unirest\Request::get(self::ACCOUNT_URL, [], ["api_key" => $this->api_key]);
        return $response->body;
    }


    private function scrape($url, $method,  $options = [], $body = null)
    {


        $headers = $options['headers'] ?: [];
        $country_code = $options['country_code'];
        $device_type = $options['device_type'];
        $premium = $options['premium'] ?: false;
        $render = $options['render'] ?: false;
        $session_number = $options['session_number'];
        $autoparse = $options['autoparse'] ?: false;
        $retry = $options['retry'] ?: 3;
        $timeout = $options['timeout'] ?: 60;

        $query = array(
            "country_code" => $country_code,
            "$device_type" => $device_type,
            "api_key" => $this->api_key,
            "premium" => $premium,
            "render" => $render,
            "session_number" => $session_number,
            "autoparse" => $autoparse,
            "keep_headers" => count($headers) > 0,
            "url" => $url,
            "scraper_sdk" => "php"
        );

        $filteredQuery = array_filter(
            $query,
            function ($val, $key) {
                if (isset($val) && is_bool($val)) {
                    if ($val) {
                        return $val;
                    }
                } else if (isset($val)) {
                    return $val;
                };

                return false;
            },
            ARRAY_FILTER_USE_BOTH
        );

        Unirest\Request::timeout($timeout);

        $queryString = http_build_query($filteredQuery);
        $scraperUrl = sprintf(self::QUERY_URL, $queryString);

        $makeRequest = function () use ($headers, $body, $scraperUrl, $method) {
            return Unirest\Request::send($method, $scraperUrl, $body, $headers);
        };

        return self::retryRequest(0, $makeRequest, $retry);
    }


    public static function retryRequest($try, $makeRequest, $max = 1)
    {
        $response = null;
        try {
            $response = $makeRequest();
            if ($response->code >= 500) {
                throw new ConnectException('Could not reach ScraperAPI Proxy');
            }
        } catch (ConnectException $e) {
            if ($try < $max) {
                sleep(1);
                $response = self::retryRequest($try + 1, $makeRequest, $max);
            } else {
                throw new ConnectException('Failed to connect after ' . $max . ' attempts');
            }
        }

        return $response;
    }
}
