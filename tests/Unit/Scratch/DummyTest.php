<?php

namespace Helio\Test\Unit;

use PHPUnit\Framework\TestCase;

class DummyTest extends TestCase {

    public function testFilters(): void {
        $this->assertNotFalse(filter_var(filter_var('spare.c.peppy-center-135409.internal', FILTER_SANITIZE_STRING), FILTER_VALIDATE_DOMAIN));
    }
}
