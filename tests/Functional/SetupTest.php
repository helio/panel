<?php

namespace Helio\Test\Functional;

use Helio\Test\TestCase;

/**
 * Class SetupTest verifies the setup of the testing environment.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class SetupTest extends TestCase
{
    public function testGlobalsAndServerEnv(): void
    {
        $this->assertTrue(\defined('APPLICATION_ROOT'));
        $this->assertFileExists(APPLICATION_ROOT . '/www/index.php');
        $this->assertFalse(strpos('//', APPLICATION_ROOT . '/www/index.php'));
        $this->assertNotEmpty($_SERVER['JWT_SECRET']);
        $this->assertNotEmpty($_SERVER['ZAPIER_HOOK_URL']);
    }
}
