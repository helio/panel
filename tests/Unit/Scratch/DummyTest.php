<?php

namespace Helio\Test\Unit;

class DummyTest extends \PHPUnit_Framework_TestCase {

    public function testFilters(): void {
        $this->assertNotFalse(filter_var(filter_var('spare.c.peppy-center-135409.internal', FILTER_SANITIZE_STRING), FILTER_VALIDATE_DOMAIN));
    }
}
