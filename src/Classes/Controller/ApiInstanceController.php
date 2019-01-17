<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\GrafanaController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\AuthorizedInstanceController;
use Helio\Panel\Instance\InstanceFactory;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Instance\InstanceType;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Runner\RunnerFactory;
use Helio\Panel\ViewModel\InstanceInfoViewModel;
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
    use AuthorizedInstanceController;
    use TypeDynamicController;
    use GrafanaController;


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
     * @Route("/cleanup", methods={"DELETE"}, name="instance.cleanup")
     */
    public function cleanupInstanceAction(): ResponseInterface
    {
        if (RunnerFactory::getRunnerForInstance($this->instance)->stopComputing() && RunnerFactory::getRunnerForInstance($this->instance)->remove() && MasterFactory::getMasterForInstance($this->instance)->cleanup()) {
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
    public function addInstanceAction(): ResponseInterface
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
            'instanelocation' => FILTER_SANITIZE_STRING,
            'instancelevel' => FILTER_SANITIZE_STRING,
            'provision' => FILTER_SANITIZE_STRING,
            'openstackEndpoint' => FILTER_SANITIZE_STRING,
            'openstackAuth' => FILTER_SANITIZE_STRING,
            'bearcloudToken' => FILTER_SANITIZE_STRING,
            'billingReference' => FILTER_SANITIZE_STRING
        ]);

        $this->instance
            ->setName($this->params['instancename'] ?? 'Automatically named during creation')
            ->setRegion($this->params['instancelocation'] ?? '')
            ->setSecurity($this->params['instancelevel'] ?? '')
            ->setBillingReference($this->params['billingReference'] ?? '');

        if ($this->params['instancetype'] === InstanceType::BEARCLOUD) {
            $this->instance->setSupervisorToken($this->params['bearcloudToken'] ?? '');
        }
        if ($this->params['instancetype'] === InstanceType::OPENSTACK) {
            $this->instance
                ->setSupervisorToken($this->params['openstackAuth'] ?? '')
                ->setSupervisorApi($this->params['openstackEndpoint']);
        }


        if (!array_key_exists('allowFree', $this->params) || $this->params['allowFree'] !== 'on') {
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
     * Get and Update Instance Status
     *
     * Hint: This method might be called recursively if the status changes.
     *
     * @return ResponseInterface
     * @Route("/status", methods={"GET"}, name="server.status")
     */
    public function getStatusAction(): ResponseInterface
    {
        $status = $this->ensureAndGetInstanceStatus();

        $data = [
            'status' => $status,
            'instance' => $this->instance
        ];

        if ($this->instance->getStatus() > InstanceStatus::CREATED) {
            $data['info'] = new InstanceInfoViewModel([OrchestratorFactory::getOrchestratorForInstance($this->instance)->getInventory(), $status]);
        }

        return $this->render(['listItemHtml' => $this->fetchPartial('listItemInstance', $data)]);
    }


    /**
     * @return ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     *
     * @Route("/metrics/snapshot/create", methods={"PUT", "GET"}, name="api.grafana.snapshot.create")
     */
    public function createSnapshotAction(): ResponseInterface
    {

        if ($json = $this->createSnapshot()) {
            $this->instance->setSnapshotConfig(json_encode($json));
            $this->persistInstance();
            return $this->render(['message' => 'created', 'snapshotUrl' => $json['url']]);
        }

        return $this->render(['status' => 'unknown'], StatusCode::HTTP_FAILED_DEPENDENCY);

    }
}