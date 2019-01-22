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
        $this->infrastructure->import($user);

        $app = null;
        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];

        /** @var App $app */
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null, [], $app);

        $this->assertEquals(200, $response->getStatusCode());
        $ct = $app->getContainer();
        $this->assertEquals($user->getId(), $app->getContainer()->get('user')->getId());
    }


    /**
     *
     * @throws \Exception
     */
    public function testLoginWithJwtInCookie(): void
    {

        $user = new User();
        $user->setId(1221)->setCreated()->setName('testuser');
        $this->infrastructure->import($user);

        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];
        $response = $this->runApp('GET', '/panel', true, $tokenCookie);

        $body = (string)$response->getBody();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('testuser', $body);
    }


    /**
     *
     * @throws \Exception
     */
    public function testLoginWithJwtInUrl(): void
    {

        $user = new User();
        $user->setId(1221)->setName('testuser');
        $this->infrastructure->import($user);

        $response = $this->runApp('GET', '/panel', true, null, null, ['token' => JwtUtility::generateToken($user->getId())['token']]);

        $body = (string)$response->getBody();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('testuser', $body);
    }


    /**
     *
     * @throws \Exception
     */
    public function testJwtMiddlewareDecoding(): void
    {

        $user = new User();
        $user->setId(564);
        $this->infrastructure->import($user);
        $app = true;

        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];

        /** @var App $app */
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null, [], $app);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($user->getId(), $app->getContainer()->get('jwt')['uid']);
    }


    /**
     *
     * @throws \Exception
     */
    public function testReAuthenticationAfterParameterLogin(): void
    {

        $user = new User();
        $user->setId(1221);
        $this->infrastructure->import($user);

        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null);
        $cookies = $response->getHeader('set-cookie');

        // guard asserts
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInternalType('array', $cookies);
        $this->assertCount(1, $cookies);

        // actual tests
        $this->assertStringStartsWith('token=', $cookies[0]);
        $this->assertStringStartsNotWith($tokenCookie, $cookies[0]);
    }
}