<?php

namespace Helio\Test\Integration;

use Helio\Test\TestCase;

class ServerApiTest extends TestCase
{


    /**
     *
     * @throws \Exception
     */
    public function testServerInit(): void {
        $data = ['fqdn' => 'testserver.example.com', 'email' => 'test@example.com'];
        $response = $this->runApp('POST', '/server/init', true, null, $data);
        $body = $response->getBody()->getContents();

        $this->markTestIncomplete();
    }
}
