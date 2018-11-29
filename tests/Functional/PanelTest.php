<?php

namespace Helio\Test\Functional;

use Helio\Panel\App;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Model\User;
use Helio\Test\TestCase;


/**
 * Class HomepageTest
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class PanelTest extends TestCase
{


    /**
     * Test that the index route returns a rendered response
     *
     * @throws \Exception
     */
    public function testGetHomeContainsWithLogin(): void
    {
        $response = $this->runApp('GET', '/');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Log In', (string)$response->getBody());
        $this->assertContains('<form', (string)$response->getBody());
        $this->assertContains('TESTSHA1', (string)$response->getBody(), 'SHA1 Hash of script not displayed');
    }


    /**
     *
     * @throws \Exception
     */
    public function testLoginWithJwtInCookie(): void
    {
        $this->markTestIncomplete('CircleCI can\'t handle this yet.');

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
    public function testLoginWithJwtInUrl(): void
    {
        $this->markTestIncomplete('CircleCI can\'t handle this yet.');

        $user = new User();
        $user->setId(1221);

        $response = $this->runApp('GET', '/panel', true, null, null, ['token' => JwtUtility::generateToken($user->getId())['token']]);

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
        $this->markTestIncomplete('CircleCI can\'t handle this yet.');

        $user = new User();
        $user->setId(564);
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
        $this->markTestIncomplete('CircleCI can\'t handle this yet.');

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