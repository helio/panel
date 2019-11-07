<?php

namespace Helio\Test\Unit;

use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Utility\ServerUtility;
use Helio\Test\TestCase;

class ServerUtilityTest extends TestCase
{
    public function testGetBaseUrl(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.com';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertEquals('http://test.com/', ServerUtility::getBaseUrl());
        $_SERVER['HTTPS'] = 'OFF';
        $this->assertEquals('http://test.com/', ServerUtility::getBaseUrl());
        $_SERVER['HTTPS'] = 'ON';
        $this->assertEquals('https://test.com/', ServerUtility::getBaseUrl());

        $_SERVER['BASE_URL'] = 'https://baseurl.example.com';
        $this->assertEquals('https://baseurl.example.com/', ServerUtility::getBaseUrl());
    }

    public function testSanitizerOfAutosignThrowsWhenInvalidCharacterInFqdn(): void
    {
        $catch = false;
        $server = (new Instance())
                ->setId(424234234)
                ->setFqdn('";sudo init 0')
                ->setMasterCoordinator('master.domain.tld')
                ->setMasterType('puppet');

        try {
            MasterFactory::getMasterForInstance($server)->doSign();
        } catch (\InvalidArgumentException $e) {
            $catch = true;
        }
        $this->assertTrue($catch);
    }

    public function testAutosignCallContainsPassedFqdn(): void
    {
        $server = (new Instance())
            ->setId(4434)
            ->setFqdn('test.server.domain.tld')
            ->setMasterCoordinator('master.domain.tld')
            ->setMasterType('puppet');
        $result = MasterFactory::getMasterForInstance($server)->doSign();

        $this->assertStringStartsWith('ssh', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString($server->getFqdn(), ServerUtility::getLastExecutedShellCommand());
    }

    public function testTimezoneObject(): void
    {
        $this->assertEquals('Europe/Berlin', ServerUtility::getTimezoneObject()->getName());
    }

    public function testReverseProxy(): void
    {
        $clientIp = '8.4.5.6';
        $reverseProxyIp = '5.2.5.55';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $clientIp;
        $_SERVER['REVERSE_PROXY_IP'] = $reverseProxyIp;
        $_SERVER['REMOTE_ADDR'] = $reverseProxyIp;

        $this->assertEquals($clientIp, ServerUtility::getClientIp());

        $_SERVER['REVERSE_PROXY_IP'] = '101.1.1.1';

        $this->assertNotEquals($clientIp, ServerUtility::getClientIp());
        $this->assertEquals($reverseProxyIp, ServerUtility::getClientIp());
    }
}
