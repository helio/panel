<?php
namespace Helio\Test\Unit;

use Helio\Panel\Utility\ServerUtility;
use Helio\Test\TestCase;

class ServerUtilityTest extends TestCase
{


    /**
     *
     */
    public function testGetBaseUrl(): void {
        $_SERVER['HTTP_HOST'] = 'test.com';
        $this->assertEquals('http://test.com/', ServerUtility::getBaseUrl());
        $_SERVER['HTTPS'] = 'OFF';
        $this->assertEquals('http://test.com/', ServerUtility::getBaseUrl());
        $_SERVER['HTTPS'] = 'ON';
        $this->assertEquals('https://test.com/', ServerUtility::getBaseUrl());
    }

}