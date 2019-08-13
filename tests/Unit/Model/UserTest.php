<?php

namespace Helio\Test\Unit;

use Helio\Panel\Model\User;
use Helio\Test\TestCase;

class UserTest extends TestCase
{
    public function testIdFunctionality(): void
    {
        $user = new User();
        $user->setId(69);
        $this->assertEquals(69, $user->getId());
    }
}
