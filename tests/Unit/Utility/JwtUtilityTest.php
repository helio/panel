<?php
namespace Helio\Test\Unit;

use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Helio\Test\Infrastructure\Model\Instance;

class JwtUtilityTest extends \PHPUnit_Framework_TestCase {


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


    /**
     *
     * @throws \Exception
     */
    public function testServerTokenGeneration(): void {
        $generated = new \DateTime('now', new \DateTimeZone('Europe/Berlin'));
        $server = new Instance();
        $server->setId(99);
        $server->setCreated($generated);

        $token = JwtUtility::generateInstanceIdentificationToken($server);
        $this->assertTrue(JwtUtility::verifyInstanceIdentificationToken($server, $token));
    }
}