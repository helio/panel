<?php
namespace Helio\Test\Unit;

use Helio\Test\Functional\Fixture\User;
use Helio\Test\TestCase;

class UserTest extends TestCase {

    public function testHashedIdFunctionality(): void {

        $user = new User();
        $user->setId(69);
        $this->assertEquals(substr(md5(69 . 'ladida'), 0, 6), $user->hashedId());
    }
}