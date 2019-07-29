<?php

namespace Helio\Panel\Controller;


use \Exception;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\App;
use \RuntimeException;
use Helio\Panel\Controller\Traits\HelperGrafanaController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\AuthorizedInstanceController;
use Helio\Panel\Instance\InstanceFactory;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Instance\InstanceType;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Runner\RunnerFactory;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
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
    use HelperGrafanaController;


    /**
     * (wenn der Token bekannt ist (zB wenn der Server im Panel erstellt worden ist))
     *
     * @return ResponseInterface
     *
     * @Route("/register", methods={"POST"}, name="server.register")
     */
    public function registerAction(): ResponseInterface
    {

        try {
            $this->requiredParameterCheck(['fqdn' => FILTER_SANITIZE_STRING]);
            $ip = filter_var(ServerUtility::getClientIp(), FILTER_VALIDATE_IP);

            if (!$this->instance) {
                throw new RuntimeException('server could not be verified', 1530915652);
            }

            if (array_key_exists('fqdn', $this->params) && $this->params['fqdn']) {
                $this->instance->setFqdn($this->params['fqdn']);
            }

            $this->instance->setIp($ip);
            $this->instance->setStatus(InstanceStatus::CREATED);
            $this->persistInstance();

            $token = MasterFactory::getMasterForInstance($this->instance)->doSign();
            if (!$token) {
                throw new RuntimeException('couldn\'t generate autosign. Please try again.', 1530917143);
            }
        } catch (Exception $e) {
            return $this->json(['success' => false, 'reason' => $e->getMessage()], StatusCode::HTTP_NOT_ACCEPTABLE);
        }

        return $this->json(['success' => true,
            'user_id' => $this->instance->getOwner()->getId(),
            'server_id' => $this->instance->getId(),
            'token' => $token]);
    }

    /**
     * @return ResponseInterface
     * @throws Exception
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
     * @throws Exception
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
     * @throws Exception
     *
     * @Route("/remove", methods={"DELETE"}, name="instance.remove")
     */
    public function removeInstanceAction(): ResponseInterface
    {
        $this->instance->setHidden(true);
        $this->persistInstance();
        return $this->render(['success' => true,'removed' => true]);
    }

    /**
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/cleanup", methods={"DELETE"}, name="instance.cleanup")
     */
    public function cleanupInstanceAction(): ResponseInterface
    {
        if (RunnerFactory::getRunnerForInstance($this->instance)->stopComputing() && RunnerFactory::getRunnerForInstance($this->instance)->remove() && MasterFactory::getMasterForInstance($this->instance)->cleanup()) {
            $this->instance->setHidden(true);
            $this->persistInstance();
            return $this->render(['success' => true,'removed' => true]);
        }
        return $this->render(['message' => ':('], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }


    /**
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/togglefree", methods={"PUT"}, name="instance.remove")
     */
    public function toggleFreeComputingAction(): ResponseInterface
    {
        $this->instance->setAllowFreeComputing(!$this->instance->isAllowFreeComputing());
        $this->persistInstance();
        return $this->render(['success' => true, 'message' => 'Free computing is now ' . $this->instance->isAllowFreeComputing() ? 'on' : 'off']);
    }


    /**
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/add", methods={"POST"}, name="instance.add")
     */
    public function addInstanceAction(): ResponseInterface
    {
        try {
            $this->requiredParameterCheck([
                'fqdn' => FILTER_SANITIZE_STRING,
                'instancetype' => FILTER_SANITIZE_STRING
            ]);
        } catch (RuntimeException $e) {
            if ($this->instance->getName() === '___NEW' && $this->instance->getStatus() === InstanceStatus::UNKNOWN) {
                $this->instance->setName('___initiating');
                $this->persistInstance();
                $token = JwtUtility::generateToken(null, $this->user, $this->instance)['token'];
                return $this->render(['token' => $token, 'id' => $this->instance->getId()]);
            }
            return $this->render(['status' => 'fail'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }

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
            'message' => 'Instance <strong>' . $this->instance->getName() . '</strong> added.',
            'id' => $this->instance->getId(),
            'token' => JwtUtility::generateToken(null, $this->user, $this->instance)['token']
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/add/abort", methods={"POST"}, name="server.abort")
     * @throws Exception
     */
    public function abortAddInstanceAction(): ResponseInterface
    {
        if ($this->instance && $this->instance->getStatus() === InstanceStatus::UNKNOWN && $this->instance->getOwner() && $this->instance->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeInstance($this->instance);
            App::getDbHelper()->remove($this->instance);
            App::getDbHelper()->flush($this->instance);
            $this->persistUser();
            return $this->render();
        }
        return $this->render(['message' => 'no access to server'], StatusCode::HTTP_UNAUTHORIZED);
    }


    /**
     * Get and Update Instance Status
     *
     * Hint: This method might be called recursively if the status changes.
     *
     * @return ResponseInterface
     * @Route("/status", methods={"GET"}, name="server.status")
     * @throws Exception
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
     * @throws GuzzleException
     * @throws Exception
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