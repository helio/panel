<?php

namespace Helio\Test\Unit;

use Helio\Panel\Model\Job;
use Helio\Test\TestCase;

class JobTest extends TestCase
{

    public function testManagerNodeRemoval(): void
    {
        $job = new Job();
        $job->addManagerNode('manager-test-10.example.com');
        $this->assertCount(1, $job->getManagerNodes());
        $job->removeManagerNode('manager-test');
        $this->assertCount(1, $job->getManagerNodes());
        $job->removeManagerNode('manager-test-10');
        $this->assertCount(0, $job->getManagerNodes());
    }
}