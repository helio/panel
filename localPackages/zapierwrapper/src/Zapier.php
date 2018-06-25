<?php

namespace Helio\ZapierWrapper;

use GuzzleHttp\Client;

class Zapier
{


    /**
     * @var Client
     */
    protected $client;


    /**
     * Zapier constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }


    /**
     * @param $method
     * @param string $url
     * @param array $options
     * @param array $urlParams
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function exec($method, string $url = '', array $options = [], array $urlParams = [])
    {
        if ($urlParams) {
            $url .= '?' . implode('&', $urlParams);
        }

        return $this->client->request($method, $url, $options);
    }
}