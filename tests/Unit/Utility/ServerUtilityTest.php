<?php

namespace Helio\Test\Unit;

use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Utility\ServerUtility;
use Helio\Test\TestCase;

class ServerUtilityTest extends TestCase
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
            $server = (new Instance())
                ->setId(424234234)
                ->setFqdn('";sudo init 0')
                ->setMasterCoordinator('master.domain.tld')
                ->setMasterType('puppet')
                ->setRunnerCoordinator('coordinator.domain.tld')
                ->setRunnerType('docker');

            MasterFactory::getMasterForInstance($server)->doSign(true);
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
        $server = (new Instance())
            ->setId(4434)
            ->setFqdn('test.server.domain.tld')
            ->setMasterCoordinator('master.domain.tld')
            ->setMasterType('puppet')
            ->setRunnerCoordinator('coordinator.domain.tld')
            ->setRunnerType('docker');
        $result = MasterFactory::getMasterForInstance($server)->doSign(true);

        $this->assertStringStartsWith('ssh', $result);
        $this->assertContains($server->getFqdn(), $result);
    }
}