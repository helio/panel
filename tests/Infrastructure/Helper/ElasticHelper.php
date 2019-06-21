<?php

namespace Helio\Test\Infrastructure\Helper;

use GuzzleHttp\Ring\Client\MockHandler;
use Elasticsearch\ClientBuilder;

class ElasticHelper extends \Helio\Panel\Helper\ElasticHelper
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(array $hosts = [])
    {
        $handler = new MockHandler([
            'status' => 200,
            'transfer_stats' => [
                'total_time' => 100
            ],
            'body' => fopen('data://text/json,{"hits":0}', 'rb'),
            'effective_url' => 'localhost'
        ]);
        $builder = ClientBuilder::create();
        $builder->setHosts(['test.elastic.host.example.com']);
        $builder->setHandler($handler);
        $this->client = $builder->build();
    }
}