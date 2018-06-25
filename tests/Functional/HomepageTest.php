<?php

namespace Helio\Test\Functional;

/**
 * Class HomepageTest
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class HomepageTest extends BaseAppCase
{

    /**
     * Test that the index route returns a rendered response
     */
    public function testGetHomepageWithoutName(): void
    {
        $response = $this->runApp('GET', '/');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('success', (string)$response->getBody());
        $this->assertNotContains('Hello', (string)$response->getBody());
    }
    /**
     * Test that the index route returns a rendered response
     */
    public function testGetHelloWithName(): void
    {
        $response = $this->runApp('GET', '/hello/max');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('max', (string)$response->getBody());
    }
    /**
     * Test that the index route returns a rendered response
     */
    public function testApiGetHelloWithName(): void
    {
        $response = $this->runApp('GET', '/api/hello/max');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('max', (string)$response->getBody());
        $this->assertNotContains('Hello', (string)$response->getBody());
        $this->assertNotNull(json_decode((string)$response->getBody()));
    }
}