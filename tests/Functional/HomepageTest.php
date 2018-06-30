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
    public function testGetHomepageWithLogin(): void
    {
        $response = $this->runApp('GET', '/');

        $this->assertContains('Login', (string)$response->getBody());
        $this->assertContains('<form', (string)$response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
    }
}