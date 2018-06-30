<?php
namespace Helio\Test\Unit;

use Helio\Panel\Helper\JwtHelper;
use Helio\Test\TestCase;

class JwtHelperTest extends TestCase {


    /**
     *
     */
    public function testGetSecretReturnsSecret(): void {
        $this->assertEquals($_SERVER['JWT_SECRET'], JwtHelper::getSecret());
    }


    /**
     *
     * @throws \Exception
     */
    public function testGenerateTokenReturnStructure() {
        $token = JwtHelper::generateToken('test');
        $this->assertTrue(array_key_exists('token', $token));
        $this->assertTrue(array_key_exists('expires', $token));
    }
}