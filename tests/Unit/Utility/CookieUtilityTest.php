<?php

namespace Helio\Test\Unit;

use Helio\Panel\Utility\CookieUtility;
use Slim\Http\Response;

class CookieUtilityTest extends \PHPUnit_Framework_TestCase
{


    /**
     *
     * @throws \Exception
     */
    public function testAddCookie(): void
    {
        $response = new Response();
        $response = CookieUtility::addCookie($response, 'test', 'value');
        $this->assertCount(1, $response->getHeader('set-cookie'));
        $this->assertContains('test=value', $response->getHeader('set-cookie')[0]);
    }


    /**
     *
     * @throws \Exception
     */
    public function testDeleteCookie(): void
    {
        $response = new Response();
        $response = CookieUtility::addCookie($response, 'test', 'value');
        $this->assertCount(1, $response->getHeader('set-cookie'));
        $this->assertContains('test=value', $response->getHeader('set-cookie')[0]);

        $response = CookieUtility::deleteCookie($response, 'test')->getHeader('set-cookie');
        $this->assertInternalType('array', $response);
        $this->assertCount(2, $response);
        $this->assertContains('test=deleted', $response[1]);
        $this->assertEquals(0, $this->getMaxAge($response[1]));
        $this->assertEquals(1, $this->getExpires($response[1])->getTimestamp());
    }


    /**
     *
     * @throws \Exception
     */
    public function testExpiresInAddCookie(): void
    {
        $emptyResponse = new Response();
        $now = new \DateTimeImmutable('now', new \DateTimeZone(CookieUtility::$timeZone));
        $future = new \DateTimeImmutable('now +2 weeks', new \DateTimeZone(CookieUtility::$timeZone));

        $responseCookies = CookieUtility::addCookie($emptyResponse, 'test', 'value',
            $future->getTimestamp())->getHeader('set-cookie');
        $this->assertInternalType('array', $responseCookies);
        $this->assertCount(1, $responseCookies);
        $this->assertCloseDateTime($future, $this->getExpires($responseCookies[0]));
        $this->assertCloseInt($future->getTimestamp() - $now->getTimestamp(), $this->getMaxAge($responseCookies[0]), 1);
    }


    /**
     * @param \DateTimeInterface $expected
     * @param \DateTimeInterface $actual
     *
     */
    protected function assertCloseDateTime(\DateTimeInterface $expected, \DateTimeInterface $actual): void
    {
        $this->assertCloseInt($expected->getTimestamp(), $actual->getTimestamp(), 1);
    }


    /**
     * @param int $expected
     * @param int $actual
     * @param int $maxDistance
     *
     */
    protected function assertCloseInt(int $expected, int $actual, int $maxDistance): void
    {
        $this->assertLessThanOrEqual($expected + $maxDistance, $actual);
        $this->assertGreaterThanOrEqual($expected - $maxDistance, $actual);
    }


    /**
     * @param string $cookieValue
     *
     * @return \DateTimeInterface
     * @throws \Exception
     */
    protected function getExpires(string $cookieValue): \DateTimeInterface
    {
        preg_match('/expires=([^;]+);/', $cookieValue, $match);

        return new \DateTimeImmutable($match[1], new \DateTimeZone(CookieUtility::$timeZone));
    }


    /**
     * @param string $cookieValue
     *
     * @return int
     * @throws \Exception
     */
    protected function getMaxAge(string $cookieValue): int
    {
        preg_match('/Max-Age=([^;]+);/', $cookieValue, $match);

        return (int)$match[1];
    }
}

