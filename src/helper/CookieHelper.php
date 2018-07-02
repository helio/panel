<?php

namespace Helio\Panel\Helper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CookieHelper
{


    /**
     * @var string
     */
    public static $timeZone = 'Europe/Berlin';


    /**
     * @param ResponseInterface $response
     * @param string $key
     *
     * @return ResponseInterface
     */
    public static function deleteCookie(ResponseInterface $response, $key): ResponseInterface
    {
        $cookie = urlencode($key) . '=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT; Max-Age=0; path=/; secure; httponly';
        $response = $response->withAddedHeader('Set-Cookie', $cookie);

        return $response;
    }


    /**
     * @param ResponseInterface $response
     * @param string $cookieName
     * @param string $cookieValue
     * @param int $expires
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public static function addCookie(ResponseInterface $response, string $cookieName, string $cookieValue, int $expires = 0): ResponseInterface
    {
        if ($expires > 0) {
            $expiry = (new \DateTimeImmutable("@$expires"))->setTimezone(new \DateTimeZone(self::$timeZone));
            $maxAge = $expires - (new \DateTime('now', new \DateTimeZone(self::$timeZone)))->getTimestamp();
        } else {
            $expiry = new \DateTimeImmutable('now + 10 minutes', new \DateTimeZone(self::$timeZone));
            $maxAge = 600;
        }
        // TODO: re-add secure.
        $cookie = urlencode($cookieName) . '=' .
            urlencode($cookieValue) . '; expires=' . $expiry->format(\DateTime::COOKIE) . '; Max-Age=' .
            $maxAge . '; path=/; httponly';
        $response = $response->withAddedHeader('Set-Cookie', $cookie);

        return $response;
    }


    /**
     * @param ServerRequestInterface $request
     * @param string $cookieName
     * @return string
     */
    public static function getCookieValue(ServerRequestInterface $request, $cookieName): string
    {
        $cookies = $request->getCookieParams();
        if (!array_key_exists($cookieName, $cookies)) {
            return null;
        }

        return $cookies[$cookieName] ?? '';
    }

}