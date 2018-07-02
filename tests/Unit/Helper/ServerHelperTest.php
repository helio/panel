<?php
namespace Helio\Test\Unit;

use Helio\Panel\Helper\ServerHelper;
use Helio\Test\TestCase;

class ServerHelperTest extends TestCase
{


    /**
     *
     */
    public function testGetBaseUrl(): void {
        $_SERVER['HTTP_HOST'] = 'test.com';
        $this->assertEquals('http://test.com/', ServerHelper::getBaseUrl());
        $_SERVER['HTTPS'] = 'OFF';
        $this->assertEquals('http://test.com/', ServerHelper::getBaseUrl());
        $_SERVER['HTTPS'] = 'ON';
        $this->assertEquals('https://test.com/', ServerHelper::getBaseUrl());
    }

}