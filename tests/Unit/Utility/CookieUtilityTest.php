<?php

namespace Helio\Test\Unit;

use Helio\Panel\Utility\CookieUtility;
use Helio\Panel\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\Response;

class CookieUtilityTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testAddCookie(): void
    {
        $response = new Response();
        $response = CookieUtility::addCookie($response, 'test', 'value');
        $this->assertCount(1, $response->getHeader('set-cookie'));
        $this->assertStringContainsString('test=value', $response->getHeader('set-cookie')[0]);
    }

    /**
     * @throws \Exception
     */
    public function testDeleteCookie(): void
    {
        $response = new Response();
        $response = CookieUtility::addCookie($response, 'test', 'value');
        $this->assertCount(1, $response->getHeader('set-cookie'));
        $this->assertStringContainsString('test=value', $response->getHeader('set-cookie')[0]);

        $response = CookieUtility::deleteCookie($response, 'test')->getHeader('set-cookie');
        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertStringContainsString('test=deleted', $response[1]);
        $this->assertEquals(0, $this->getMaxAge($response[1]));
        $this->assertEquals(1, $this->getExpires($response[1])->getTimestamp());
    }

    /**
     * @throws \Exception
     */
    public function testExpiresInAddCookie(): void
    {
        $emptyResponse = new Response();
        $now = new \DateTimeImmutable('now', ServerUtility::getTimezoneObject());
        $future = new \DateTimeImmutable('now +2 weeks', ServerUtility::getTimezoneObject());

        $responseCookies = CookieUtility::addCookie(
            $emptyResponse,
            'test',
            'value',
            $future->getTimestamp()
        )->getHeader('set-cookie');
        $this->assertIsArray($responseCookies);
        $this->assertCount(1, $responseCookies);
        $this->assertEqualsWithDelta($future, $this->getExpires($responseCookies[0]), 1.0);
        $this->assertEqualsWithDelta($future->getTimestamp() - $now->getTimestamp(), $this->getMaxAge($responseCookies[0]), 1.0);
    }

    /**
     * @param string $cookieValue
     *
     * @return \DateTimeInterface
     *
     * @throws \Exception
     */
    protected function getExpires(string $cookieValue): \DateTimeInterface
    {
        preg_match('/expires=([^;]+);/', $cookieValue, $match);

        return new \DateTimeImmutable($match[1], ServerUtility::getTimezoneObject());
    }

    /**
     * @param string $cookieValue
     *
     * @return int
     *
     * @throws \Exception
     */
    protected function getMaxAge(string $cookieValue): int
    {
        preg_match('/Max-Age=([^;]+);/', $cookieValue, $match);

        return (int) $match[1];
    }
}
