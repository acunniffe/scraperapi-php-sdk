<?php
declare(strict_types=1);

namespace ScraperAPI;

use Exception;
use Unirest\Method;
use Unirest\Request;
use function count;

/**
 * Class Client
 * @package ScraperAPI
 */
class Client
{
    public $apiKey;

    /**
     * Client constructor.
     * @param string $key
     */
    public function __construct($key)
    {
        $this->apiKey = $key;
    }

    /**
     * @param string $url
     * @param array $options
     * @return mixed
     * @throws Exception
     */
    public function get($url, $options = [])
    {
        return $this->scrape($url, Method::GET, $options, null);
    }

    /**
     * @param $url
     * @param array $options
     * @param $body
     * @return mixed
     * @throws Exception
     */
    public function post($url, $options = [], $body)
    {
        return $this->scrape($url, Method::POST, $options, $body);
    }

    /**
     * @param $url
     * @param array $options
     * @param $body
     * @return mixed
     * @throws Exception
     */
    public function put($url, $options = [], $body)
    {
        return $this->scrape($url, Method::PUT, $options, $body);
    }

    /**
     * @return mixed
     */
    public function account()
    {
        $response = Request::get('https://api.scraperapi.com/account', [], ["api_key" => $this->apiKey]);

        return $response->body;
    }

    /**
     * @param $url
     * @param $method
     * @param array $options
     * @param $body
     * @return mixed
     * @throws Exception
     */
    private function scrape($url, $method, $options = [], $body)
    {
        $headers = $options['headers'] ?? [];
        $countryCode = $options['country_code'] ?? '';
        $premium = $options['premium'] ?? false;
        $render = $options['render'] ?? false;
        $sessionNumber = $options['session_number'] ?? '';
        $autoparse = $options['autoparse'] ?? false;
        $retry = $options['retry'] ?? 3;
        $timeout = $options['timeout'] ?? 60;

        $query = [
            'country_code' => $countryCode,
            'api_key' => $this->apiKey,
            'premium' => $premium,
            'render' => $render,
            'session_number' => $sessionNumber,
            'autoparse' => $autoparse,
            'keep_headers' => count($headers) > 0,
            'url' => $url,
            'scraper_sdk' => 'php'
        ];

        $filteredQuery = array_filter(
            $query,
            static function ($val, $key) {
                if (isset($val) && is_bool($val)) {
                    if ($val) {
                        return $val;
                    }
                } else if (isset($val)) {
                    return $val;
                }
            },
            ARRAY_FILTER_USE_BOTH
        );

        Request::timeout($timeout);

        $queryString = http_build_query($filteredQuery);
        $scraperUrl = 'http://api.scraperapi.com/?' . $queryString;

        $makeRequest = function () use ($headers, $body, $scraperUrl, $method) {
            return Request::send($method, $scraperUrl, $body, $headers);
        };

        return $this->retryRequest(0, $makeRequest, $retry);
    }

    /**
     * @param $try
     * @param $makeRequest
     * @param int $max
     * @return mixed
     * @throws Exception
     */
    private function retryRequest($try, $makeRequest, $max = 1)
    {
        $response = null;
        try {
            $response = $makeRequest();
            if ($response->code >= 500) {
                throw new Exception('Could not reach ScraperAPI Proxy');
            }
        } catch (Exception $e) {
            if ($try < $max) {
                sleep(1);
                $response = $this->retryRequest($try + 1, $makeRequest, $max);
            } else {
                throw new Exception('Failed to connect after ' . $max . ' attempts');
            }
        }

        return $response;
    }
}
