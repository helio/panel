<?php

namespace Helio\Test\Unit;

use Helio\Panel\Utility\ServerUtility;

class ServerUtilityTest extends \PHPUnit_Framework_TestCase
{


    /**
     *
     */
    public function testGetBaseUrl(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.com';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertEquals('http://test.com/', ServerUtility::getBaseUrl());
        $_SERVER['HTTPS'] = 'OFF';
        $this->assertEquals('http://test.com/', ServerUtility::getBaseUrl());
        $_SERVER['HTTPS'] = 'ON';
        $this->assertEquals('https://test.com/', ServerUtility::getBaseUrl());
    }


    /**
     *
     */
    public function testSanitizerOfAutosignThrowsWhenInvalidCharacterInFqdn(): void
    {
        $catch = false;
        try {
            ServerUtility::submitAutosign('asdf;test.com', true);
        } catch (\InvalidArgumentException $e) {
            $catch = true;
        }
        $this->assertTrue($catch);
    }


    /**
     *
     */
    public function testAutosignCallContainsPassedFqdn(): void
    {
        $fqdn = 'test.server.domain.com';
        $result = ServerUtility::submitAutosign($fqdn, true);

        $this->assertStringStartsWith('ssh', $result);
        $this->assertContains($fqdn, $result);
    }
}