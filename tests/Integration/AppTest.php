<?php

namespace Helio\Test\Integration;

use Helio\Panel\App;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;

class AppTest extends TestCase
{


    /**
     *
     * @throws \Exception
     */
    public function testLoadUserFromJwtMiddleware(): void
    {

        $user = new User();
        $this->infrastructure->import($user);

        $app = true;
        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];

        /** @var App $app */
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null, [], $app);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($user->getId(), $app->getContainer()->get('user')->getId());
    }
}