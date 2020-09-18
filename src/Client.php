<?php
namespace ScraperAPI {

    use Unirest\Method;
    use Unirest\Request;

  class Client
  {
    public $api_key;

    public function __construct($key)
    {
      $this->api_key = $key;
    }

    public function get($url,
                        $options = [])
    {
      return $this->scrape($url, Method::GET, $options, null);
    }

    public function post($url,
                         $options = [],
                         $body)
    {
      return $this->scrape($url, Method::POST, $options, $body);
    }

    public function put($url,
                        $options = [],
                        $body)
    {
      return $this->scrape($url, Method::PUT, $options, $body);
    }

    public function account()
    {
      $response = Request::get('https://api.scraperapi.com/account', [], ["api_key" => $this->api_key]);
      return $response->body;
    }


    private function scrape($url,
                            $method,
                            $options = [],
                            $body)
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
        },
        ARRAY_FILTER_USE_BOTH
      );

      Request::timeout($timeout);

      $queryString = http_build_query($filteredQuery);
      $scraperUrl = "http://api.scraperapi.com/?" . $queryString;

      $makeRequest = function () use ($headers, $body, $scraperUrl, $method) {
        return Request::send($method, $scraperUrl, $body, $headers);
      };

      return retryRequest(0, $makeRequest, $retry);
    }


  }

  function retryRequest($try, $makeRequest, $max = 1)
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
        $response = retryRequest($try + 1, $makeRequest, $max);
      } else {
        throw new Exception('Failed to connect after ' . $max . ' attempts');
      }
    }

    return $response;
  }
//  $client = new Client("56d4e886e80d1a3ab41c323a216b16d4");
//$result = $client->get("http://httpbin.org/ip");
//var_dump($result->raw_body);

}

