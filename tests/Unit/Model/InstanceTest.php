<?php

namespace Helio\Test\Unit;

use Helio\Panel\Model\Instance;
use Helio\Test\TestCase;

class InstanceTest extends TestCase
{
    public function testIdFunctionality(): void
    {
        $instance = new Instance();
        $instance->setId(69);
        $this->assertEquals(69, $instance->getId());
    }

    public function testTimestampOnCreated(): void
    {
        $instance = new Instance();
        $instance->setCreated();
        $this->assertEquals($instance->getTimezone(), $instance->getCreated()->getTimezone()->getName());
    }
}
