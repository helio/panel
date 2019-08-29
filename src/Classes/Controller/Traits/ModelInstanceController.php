<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Exception\HttpException;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Slim\Http\StatusCode;

/**
 * Trait ModelInstanceController.
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
     * @param RouteInfo $route
     *
     * @throws Exception
     */
    public function setupInstance(RouteInfo $route): void
    {
        $this->setupUser();

        // if the instance has been set by JWT, we're fine
        if (App::getApp()->getContainer()->has('instance')) {
            $this->instance = App::getApp()->getContainer()->get('instance');
            $this->persistInstance();

            return;
        }

        // if requested by params, pick the instance by query
        $this->setupParams($route);
        $instanceId = filter_var($this->params['instanceid'] ?? ('instanceid' === $this->getIdAlias() ? (array_key_exists('id', $this->params) ? $this->params['id'] : 0) : 0), FILTER_VALIDATE_INT);
        if ($instanceId > 0) {
            $this->instance = App::getDbHelper()->getRepository(Instance::class)->find($instanceId);

            return;
        }

        // finally, if there is no instance, simply create one.
        $this->instance = (new Instance())
            ->setName('___NEW')
            ->setStatus(InstanceStatus::UNKNOWN);

        return;
    }

    /**
     * @throws Exception
     */
    public function validateInstanceIsSet(): void
    {
        if ($this->instance) {
            if ('___NEW' !== $this->instance->getName()) {
                $this->persistInstance();
            }

            return;
        }

        throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'No instance found');
    }

    /**
     * Persist.
     *
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
     *
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
                $status = OrchestratorFactory::getOrchestratorForInstance($this->instance)->inspect();
                if (is_array($status) && count($status) > 0) {
                    $status = $status[0];
                }
                if (is_array($status) && array_key_exists('Status', $status) && array_key_exists('State', $status['Status']) && 'ready' === $status['Status']['State']) {
                    $this->instance->setStatus(InstanceStatus::RUNNING);
                    $this->persistInstance();

                    return $this->getStatusAction();
                }

                return $status;
                break;
            case InstanceStatus::RUNNING:
                $status = OrchestratorFactory::getOrchestratorForInstance($this->instance)->inspect();
                if (is_array($status) && count($status) > 0) {
                    $status = $status[0];
                }
                if (is_array($status) && array_key_exists('Status', $status) && array_key_exists('State', $status['Status']) && 'down' === $status['Status']['State']) {
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
