<?php

namespace Helio\Test\Integration;

use GuzzleHttp\Psr7\Response;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\TestCase;

class ApiAuthenticationTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testApiLoginWithoutBodyYieldsBadRequest(): void
    {
        $response = $this->runWebApp('POST', '/api/login');

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($body, [
            'success' => false,
            'error' => 'JSON body required',
        ]);
    }

    /**
     * @throws \Exception
     */
    public function testApiLoginWithEmptyBodyYieldsBadRequest(): void
    {
        $response = $this->runWebApp('POST', '/api/login', false, ['Content-Type' => 'application/json'], []);

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($body, [
            'success' => false,
            'error' => 'Valid email is required',
        ]);
    }

    /**
     * @throws \Exception
     */
    public function testApiLoginWithWrongContentTypeYieldsBadRequest(): void
    {
        $response = $this->runWebApp('POST', '/api/login', false, ['Content-Type' => 'text/plain'], ['email' => 'email@example.com']);

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($body, [
            'success' => false,
            'error' => 'JSON body required',
        ]);
    }

    /**
     * @throws \Exception
     */
    public function testApiLoginWithInvalidEmailYieldsBadRequest(): void
    {
        $response = $this->runWebApp('POST', '/api/login', false, ['Content-Type' => 'application/json'], ['email' => 'wrong email']);

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($body, [
            'success' => false,
            'error' => 'Valid email is required',
        ]);
    }

    /**
     * @throws \Exception
     */
    public function testApiLoginWithExampleUserGivesToken(): void
    {
        ZapierHelper::setResponseStack([new Response(200, [], '{"success" => "true"}')]);
        $response = $this->runWebApp('POST', '/api/login', false, ['Content-Type' => 'application/json'], ['email' => 'email@example.com']);

        $this->assertEquals(200, $response->getStatusCode(), $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($body['user'], ['id' => 1]);
        // JWTs always start with eyJ
        $this->assertStringContainsString('eyJ', $body['token']);
    }

    /**
     * @throws \Exception
     */
    public function testApiLoginWithRandomUserGivesNoTokenAndNoUser(): void
    {
        ZapierHelper::setResponseStack([new Response(200, [], '{"success" => "true"}')]);
        $response = $this->runWebApp('POST', '/api/login', false, ['Content-Type' => 'application/json'], ['email' => 'someone@example.com']);

        $this->assertEquals(200, $response->getStatusCode(), $response->getBody());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertNull($body['user']);
        $this->assertNull($body['token']);
    }
}
