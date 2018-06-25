<?php

namespace Helio\Test\Functional;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Test\TestCase;

/**
 * Class BaseDatabaseCase
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class BaseRequestCase extends TestCase
{


    /**
     * @param string $method
     * @param string $url
     * @param array $urlParams
     * @param array $options
     * @param int $status
     * @param array $headers
     * @param string|null $body
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function call(
        string $method = 'GET',
        string $url = '',
        array $urlParams = [],
        array $options = [],
        int $status = 200,
        array $headers = [],
        string $body = null
    ) {
        $mock = new MockHandler([
            new Response($status, $headers, $body),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);

        return ZapierHelper::get($mock)->exec($method, $url, $urlParams, $options);
    }
}