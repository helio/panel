<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\ValidatedInstanceController;
use Helio\Panel\Instance\InstanceFactory;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Instance\InstanceType;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Runner\RunnerFactory;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/instance')
 *
 */
class ApiInstanceController extends AbstractController
{
    use ValidatedInstanceController;
    use TypeDynamicController;

    /**
     * @return ResponseInterface
     *
     * @Route("/stop", methods={"PUT"}, name="instance.stop")
     */
    public function stopServerAction(): ResponseInterface
    {
        if (RunnerFactory::getRunnerForInstance($this->instance)->stopComputing()) {
            $this->instance->setStatus(InstanceStatus::READY);
            $this->persistInstance();
            return $this->render();
        }
        return $this->render(['message' => ':('], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/start", methods={"PUT"}, name="instance.start")
     */
    public function startInstanceAction(): ResponseInterface
    {
        if (RunnerFactory::getRunnerForInstance($this->instance)->startComputing()) {
            $this->instance->setStatus(InstanceStatus::RUNNING);
            $this->persistInstance();
            return $this->render(['message' => 'worked!']);
        }
        return $this->render(['message' => ':('], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/remove", methods={"DELETE"}, name="instance.remove")
     */
    public function removeInstanceAction(): ResponseInterface
    {
        $this->instance->setHidden(true);
        $this->persistInstance();
        return $this->render();
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/destroy", methods={"DELETE"}, name="instance.remove")
     */
    public function destroyInstanceAction(): ResponseInterface
    {
        if (RunnerFactory::getRunnerForInstance($this->instance)->stopComputing()) {
            $this->instance->setHidden(true);
            $this->persistInstance();
            return $this->render();
        }
        return $this->render(['message' => ':('], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/togglefree", methods={"PUT"}, name="instance.remove")
     */
    public function toggleFreeComputingAction(): ResponseInterface
    {
        $this->instance->setAllowFreeComputing(!$this->instance->isAllowFreeComputing());
        $this->persistInstance();
        return $this->render();
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/add", methods={"POST"}, name="instance.add")
     * @throws \Exception
     */
    public function addServerAction(): ResponseInterface
    {
        $this->requiredParameterCheck([
            'fqdn' => FILTER_SANITIZE_STRING,
            'instancetype' => FILTER_SANITIZE_STRING
        ]);

        $this->instance
            ->setFqdn($this->params['fqdn'])
            ->setInstanceType($this->params['instancetype'])
            ->setStatus(InstanceStatus::INIT)
            ->setOwner($this->user);


        $this->optionalParameterCheck([
            'instancename' => FILTER_SANITIZE_STRING,
            'region' => FILTER_SANITIZE_STRING,
            'provision' => FILTER_SANITIZE_STRING,
            'openstackEndpoint' => FILTER_SANITIZE_STRING,
            'openstackAuth' => FILTER_SANITIZE_STRING,
            'bearcloudToken' => FILTER_SANITIZE_STRING,
            'billingReference' => FILTER_SANITIZE_STRING
        ]);

        $this->instance
            ->setName($this->params['instancename'] ?? 'Automatically named during creation')
            ->setRegion($this->params['region'] ?? '')
            ->setBillingReference($this->params['billingReference'] ?? '');

        if ($this->params['instancetype'] === InstanceType::BEARCLOUD) {
            $this->instance->setSupervisorToken($this->params['bearcloudToken'] ?? '');
        }
        if ($this->params['instancetype'] === InstanceType::OPENSTACK) {
            $this->instance
                ->setSupervisorToken($this->params['openstackAuth'] ?? '')
                ->setSupervisorApi($this->params['openstackEndpoint']);
        }


        if (!array_key_exists('allowFree', $this->params) && $this->params['allowFree'] !== 'on') {
            $this->instance->setAllowFreeComputing(false);
        }
        if (array_key_exists('provision', $this->params) && $this->params['provision'] === 'on' && InstanceFactory::getInstanceForServer($this->instance)->provisionInstance()) {
            $this->persistInstance();
            $this->instance->setStatus(InstanceStatus::CREATED);
        }

        $this->persistInstance();

        return $this->render([
            'html' => $this->fetchPartial('listItemInstance', ['instance' => $this->instance, 'user' => $this->user]),
            'message' => 'Instance <strong>' . $this->instance->getName() . '</strong> added.'
        ]);
    }


    /**
     * @return ResponseInterface
     * @Route("/status", methods={"GET"}, name="server.status")
     */
    public function getStatusAction(): ResponseInterface
    {
        if ($this->instance->getStatus() > InstanceStatus::CREATED) {
            return $this->render(['status' => RunnerFactory::getRunnerForInstance($this->instance)->inspect()]);
        }

        if ($this->instance->getStatus() === InstanceStatus::CREATED) {
            return $this->render(['status' => MasterFactory::getMasterForInstance($this->instance)->getStatus()]);
        }
        return $this->render(['message' => 'not ready yet']);
    }

}