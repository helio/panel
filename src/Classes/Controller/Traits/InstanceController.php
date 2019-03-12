<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Runner\RunnerFactory;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;

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
     * optionally create a new default instance if none is passed.
     *
     * @return bool
     * @throws \Exception
     */
    public function setupInstance(): bool
    {
        $this->setupParams();

        // make it possible to add a new job via api
        /** @noinspection NotOptimalIfConditionsInspection */
        if (property_exists($this, 'user') && $this->user !== null && \array_key_exists('instanceid', $this->params) && filter_var($this->params['instanceid'], FILTER_SANITIZE_STRING) === '_NEW') {

            $instance = (new Instance())
                ->setName('precreated automatically')
                ->setStatus(0)
                ->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
            $this->dbHelper->persist($instance);
            $this->dbHelper->flush($instance);

            $instance->setToken(JwtUtility::generateInstanceIdentificationToken($instance))
                ->setOwner($this->user);

            $this->user->addInstance($instance);
            $this->dbHelper->persist($instance);
            $this->dbHelper->flush($instance);
            $this->persistUser();
            return true;
        }

        $instanceId = filter_var($this->params['instanceid'] ?? 0, FILTER_VALIDATE_INT);
        if ($instanceId === 0) {
            $this->instance = (new Instance())->setId(0);
            return true;
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
                $status = RunnerFactory::getRunnerForInstance($this->instance)->inspect();
                if (\is_array($status) && \count($status) > 0) {
                    $status = $status[0];
                }
                if (\is_array($status) && \array_key_exists('Status', $status) && \array_key_exists('State', $status['Status']) && $status['Status']['State'] === 'ready') {
                    $this->instance->setStatus(InstanceStatus::RUNNING);
                    $this->persistInstance();
                    return $this->getStatusAction();
                }
                return $status;
                break;
            case InstanceStatus::RUNNING:
                $status = RunnerFactory::getRunnerForInstance($this->instance)->inspect();
                if (\is_array($status) && \count($status) > 0) {
                    $status = $status[0];
                }
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