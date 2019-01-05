<?php
namespace Helio\Test\Unit;

use Helio\Test\Infrastructure\Model\Instance;

class InstanceTest extends \PHPUnit_Framework_TestCase {

    public function testIdFunctionality(): void {

        $instance = new Instance();
        $instance->setId(69);
        $this->assertEquals(69, $instance->getId());
    }
}