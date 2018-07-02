<?php

namespace Helio\Test\Functional;

use Helio\Panel\Helper\JwtHelper;
use Helio\SlimWrapper\Slim;
use Helio\Test\Functional\Fixture\User;


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
    public function testUserHashedId(): void
    {
        $user = new User();
        $user->setId(69);
        $user->setEmail('test@dummy.com');

        $this->assertEquals(substr(md5(69 . 'ladida'), 0, 6), $user->hashedId());
    }

    /**
     *
     * @throws \Exception
     */
    public function testLoginWithJwt(): void {
        $user = new User();
        $user->setId(1221);

        $tokenCookie = 'token=' . JwtHelper::generateToken($user->hashedId())['token'];
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Panel', (string)$response->getBody());
    }


    /**
     *
     * @throws \Exception
     */
    public function testJwtMiddlewareDecoding(): void {

        $user = new User();
        $user->setId(564);
        $app = true;

        $tokenCookie = 'token=' . JwtHelper::generateToken($user->hashedId())['token'];

        /** @var Slim $app */
        $response = $this->runApp('GET', '/panel', true, $tokenCookie, null, $app);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($user->hashedId(), $app->getApp()->getContainer()->get('jwt')['uid']);

    }


    /**
     *
     * @throws \Exception
     */
    public function testReAuthenticationAfterParameterLogin(): void {

        $user = new User();
        $user->setId(1221);

        $tokenCookie = 'token=' . JwtHelper::generateToken($user->hashedId())['token'];
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