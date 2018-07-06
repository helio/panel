<?php

namespace Helio\Test\Functional;

use Helio\Panel\App;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Functional\Fixture\Model\User;


/**
 * Class HomepageTest
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class PanelTest extends BaseAppCase
{


    /**
     * Test that the index route returns a rendered response
     */
    public function testGetHomeContainsWithLogin(): void
    {
        $response = $this->runApp('GET', '/');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Login', (string)$response->getBody());
        $this->assertContains('<form', (string)$response->getBody());
    }


    /**
     *
     * @throws \Exception
     */
    public function testLoginWithJwt(): void
    {
        $user = new User();
        $user->setId(1221);

        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null);

        $body = (string)$response->getBody();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Panel', $body);
    }


    /**
     *
     * @throws \Exception
     */
    public function testJwtMiddlewareDecoding(): void
    {

        $user = new User();
        $user->setId(564);
        $app = true;

        $tokenCookie = 'token=' . JwtUtility::generateToken($user->getId())['token'];

        /** @var App $app */
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null, $app);

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