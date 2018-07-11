<?php
namespace Helio\Test\Unit;

use Helio\Test\Infrastructure\Model\User;

class UserTest extends \PHPUnit_Framework_TestCase {

    public function testIdFunctionality(): void {

        $user = new User();
        $user->setId(69);
        $this->assertEquals(69, $user->getId());
    }
}