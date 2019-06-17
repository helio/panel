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
        $this->assertStringContainsString('Log In', (string)$response->getBody());
        $this->assertStringContainsString('<form', (string)$response->getBody());
    }
}