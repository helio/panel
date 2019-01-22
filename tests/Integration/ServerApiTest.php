<?php

namespace Helio\Test\Integration;

use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\TestCase;
use GuzzleHttp\Psr7\Response;

class ServerApiTest extends TestCase
{

    /**
     *
     * @throws \Exception
     */
    public function testServerInit(): void
    {
        ZapierHelper::getInstance()->setResponseStack([
            new Response(200, [], '{"success" => "true"}'),
        ]);
        $data = ['fqdn' => 'testserver.example.com', 'email' => 'test@example.com'];
        $response = $this->runApp('POST', '/server/init', true, null, $data);

        $test = $response->getBody()->getContents();
        $this->assertEquals(200, $response->getStatusCode());
    }
}
