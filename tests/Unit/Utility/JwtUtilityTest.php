<?php
namespace Helio\Test\Unit;

use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Helio\Test\TestCase;

class JwtUtilityTest extends TestCase {


    /**
     *
     */
    public function testGetSecretReturnsSecret(): void {
        $this->assertEquals($_SERVER['JWT_SECRET'], ServerUtility::get('JWT_SECRET'));
    }


    /**
     *
     * @throws \Exception
     */
    public function testGenerateTokenReturnStructure(): void {
        $token = JwtUtility::generateToken('test');
        $this->assertTrue(array_key_exists('token', $token));
        $this->assertTrue(array_key_exists('expires', $token));
    }
}