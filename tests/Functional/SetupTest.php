<?php
namespace Helio\Test\Functional;

use Helio\Test\TestCase;

class SetupTest extends TestCase {


    /**
     *
     */
    public function testSetup(): void {
        $this->assertTrue(\defined('APPLICATION_ROOT'));
        $this->assertFileExists(APPLICATION_ROOT . '/www/index.php');
        $this->assertFalse(strpos('//', APPLICATION_ROOT . '/www/index.php'));
    }
}