<?php

namespace Helio\Panel\Controller\Traits;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Runner\RunnerFactory;


/**
 * Trait ModelInstanceController
 * @package Helio\Panel\Controller\Traits
 */
trait ModelInstanceController
{
    use ModelUserController;
    use ModelParametrizedController;

    /**
     * @var Instance
     */
    protected $instance;


    /**
     * optionally create a new default instance if none is passed.
     *
     * @return bool
     * @throws Exception
     */
    public function setupInstance(): bool
    {
        $this->setupUser();

        // if the instance has been set by JWT, we're fine
        if (App::getApp()->getContainer()->has('instance')) {
            $this->instance = App::getApp()->getContainer()->get('instance');
            $this->persistInstance();
            return true;
        }

        // if requested by params, pick the instance by query
        $this->setupParams();
        $instanceId = filter_var($this->params['instanceid'] ?? 0, FILTER_VALIDATE_INT);
        if ($instanceId > 0) {
            $this->instance = App::getDbHelper()->getRepository(Instance::class)->find($instanceId);
            return true;
        }

        // finally, if there is no instance, simply create one.
        $this->instance = (new Instance())
            ->setName('___NEW')
            ->setStatus(InstanceStatus::UNKNOWN)
            ->setOwner($this->user)
            ->setCreated();
        return true;
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function validateInstanceIsSet(): bool {
        if ($this->instance) {
            if ($this->instance->getName() !== '___NEW') {
                $this->persistInstance();
            }
            return true;
        }
        return false;
    }


    /**
     * Persist
     * @throws Exception
     */
    protected function persistInstance(): void
    {
        if ($this->instance) {
            $this->instance->setLatestAction();
            App::getDbHelper()->persist($this->instance);
            App::getDbHelper()->flush($this->instance);
        }
    }


    /**
     * Note: This method is called recursively via getStatusAction() which calls this method again.
     *
     * @return mixed|string
     * @throws Exception
     */
    protected function ensureAndGetInstanceStatus()
    {
        switch ($this->instance->getStatus()) {
            case InstanceStatus::CREATED:
                $status = MasterFactory::getMasterForInstance($this->instance)->getStatus();
                if (is_array($status) && array_key_exists('deactivated', $status) && !$status['deactivated']) {
                    $this->instance->setStatus(InstanceStatus::READY);
                    $this->persistInstance();
                    return $this->getStatusAction();
                }
                return $status;
                break;
            case InstanceStatus::READY:
                $status = RunnerFactory::getRunnerForInstance($this->instance)->inspect();
                if (is_array($status) && count($status) > 0) {
                    $status = $status[0];
                }
                if (is_array($status) && array_key_exists('Status', $status) && array_key_exists('State', $status['Status']) && $status['Status']['State'] === 'ready') {
                    $this->instance->setStatus(InstanceStatus::RUNNING);
                    $this->persistInstance();
                    return $this->getStatusAction();
                }
                return $status;
                break;
            case InstanceStatus::RUNNING:
                $status = RunnerFactory::getRunnerForInstance($this->instance)->inspect();
                if (is_array($status) && count($status) > 0) {
                    $status = $status[0];
                }
                if (is_array($status) && array_key_exists('Status', $status) && array_key_exists('State', $status['Status']) && $status['Status']['State'] === 'down') {
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