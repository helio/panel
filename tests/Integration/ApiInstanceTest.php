<?php

namespace Helio\Test\Integration;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;

class ApiInstanceTest extends TestCase
{

    /**
     *
     * @throws \Exception
     */
    public function testLoadUserFromJwtMiddleware(): void
    {

        $user = new User();
        $this->infrastructure->import($user);

        $response = $this->runApp('POST', '/api/instance/add?instanceid=_NEW', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);
$debug = (string)$response->getBody();
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('token', $body);
    }
}