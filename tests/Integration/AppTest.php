<?php

namespace Helio\Test\Integration;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;

class AppTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testLoadUserFromJwtMiddleware(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $this->infrastructure->import($user);

        $tokenCookie = ['token' => JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runApp('GET', '/panel', true, null, null, $tokenCookie);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($user->getId(), \Helio\Test\Infrastructure\App::getTestApp()->getContainer()->get('user')->getId());
    }

    /**
     * @throws \Exception
     */
    public function testLoginWithJwtInCookie(): void
    {
        $user = new User();
        $user->setId(1221)->setCreated()->setName('testuser');
        $this->infrastructure->import($user);

        $tokenCookie = ['token' => JwtUtility::generateToken(null, $user)['token']];
        $response = $this->runApp('GET', '/panel', true, null, null, $tokenCookie);

        $body = (string) $response->getBody();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('testuser', $body);
    }

    /**
     * @throws \Exception
     */
    public function testLoginWithJwtInUrl(): void
    {
        $user = new User();
        $user->setId(1221)->setName('testuser')->setActive(true);
        $this->infrastructure->import($user);

        $response = $this->runApp('GET', '/panel', true, null, null, ['token' => JwtUtility::generateToken(null, $user)['token']]);

        $body = (string) $response->getBody();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('testuser', $body);
    }

    /**
     * @throws \Exception
     */
    public function testJwtMiddlewareDecoding(): void
    {
        $user = new User();
        $user->setId(564);
        $this->infrastructure->import($user);

        $tokenCookie = ['token' => JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runApp('GET', '/panel', true, null, null, $tokenCookie);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($user->getId(), \Helio\Test\Infrastructure\App::getTestApp()->getContainer()->get('user')->getId());
    }

    /**
     * @throws \Exception
     */
    public function testReAuthenticationAfterParameterLogin(): void
    {
        $user = new User();
        $user->setId(1221);
        $this->infrastructure->import($user);

        $tokenCookie = ['token' => JwtUtility::generateToken(null, $user)['token']];
        $response = $this->runApp('GET', '/panel', true, null, null, $tokenCookie);
        $cookies = $response->getHeader('set-cookie');

        // guard asserts
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertIsArray($cookies);
        $this->assertCount(1, $cookies);

        // actual tests
        $this->assertStringStartsWith('token=', $cookies[0]);
        $this->assertStringNotContainsString($tokenCookie['token'], $cookies[0]);
    }
}
