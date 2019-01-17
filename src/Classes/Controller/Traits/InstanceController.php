<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Runner\RunnerFactory;

/**
 * Trait ServerController
 * @package Helio\Panel\Controller\Traits
 * @method User getUser()
 * @method bool hasUser()
 */
trait InstanceController
{
    use ParametrizedController;

    /**
     * @var Instance
     */
    protected $instance;


    /**
     * @return bool
     */
    public function setupInstance(): bool
    {
        $this->setupParams();
        $instanceId = filter_var($this->params['instanceid'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        if ($instanceId === 0) {
            return false;
        }
        $this->instance = $this->dbHelper->getRepository(Instance::class)->find($instanceId);
        return true;
    }

    /**
     * Persist
     */
    protected function persistInstance(): void
    {
        $this->instance->setLatestAction();
        $this->dbHelper->persist($this->instance);
        $this->dbHelper->flush($this->instance);
    }

    /**
     * @return mixed
     */
    protected function ensureAndGetInstanceStatus()
    {
        switch ($this->instance->getStatus()) {
            case InstanceStatus::CREATED:
                $status = MasterFactory::getMasterForInstance($this->instance)->getStatus();
                if (\is_array($status) && \array_key_exists('deactivated', $status) && !$status['deactivated']) {
                    $this->instance->setStatus(InstanceStatus::READY);
                    $this->persistInstance();
                    return $this->getStatusAction();
                }
                return $status;
                break;
            case InstanceStatus::READY:
                $status = RunnerFactory::getRunnerForInstance($this->instance)->inspect()[0];
                if (\is_array($status) && \array_key_exists('Status', $status) && \array_key_exists('State', $status['Status']) && $status['Status']['State'] === 'ready') {
                    $this->instance->setStatus(InstanceStatus::RUNNING);
                    $this->persistInstance();
                    return $this->getStatusAction();
                }
                return $status;
                break;
            case InstanceStatus::RUNNING:
                $status = RunnerFactory::getRunnerForInstance($this->instance)->inspect()[0];
                if (\is_array($status) && \array_key_exists('Status', $status) && \array_key_exists('State', $status['Status']) && $status['Status']['State'] === 'down') {
                    $this->instance->setStatus(InstanceStatus::READY);
                    $this->persistInstance();
                    return $this->getStatusAction();
                }
                return $status;
                break;
            default:
                return 'not ready yet';
                break;
        }
    }
}