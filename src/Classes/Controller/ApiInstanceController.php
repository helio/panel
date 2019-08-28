<?php

namespace Helio\Panel\Controller;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use RuntimeException;
use Helio\Panel\Controller\Traits\HelperGrafanaController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\AuthorizedInstanceController;
use Helio\Panel\Instance\InstanceFactory;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Instance\InstanceType;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Helio\Panel\ViewModel\InstanceInfoViewModel;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/instance')
 */
class ApiInstanceController extends AbstractController
{
    use AuthorizedInstanceController;
    use TypeDynamicController;
    use HelperGrafanaController;

    /**
     * @return string
     */
    protected function getIdAlias(): string
    {
        return 'instanceid';
    }

    /**
     * (wenn der Token bekannt ist (zB wenn der Server im Panel erstellt worden ist)).
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

            $this->instance->setOwner($this->user)
                ->setIp($ip)
                ->setStatus(InstanceStatus::CREATED)
                ->setCreated();
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
            'token' => $token, ]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/stop", methods={"PUT"}, name="instance.stop")
     */
    public function stopServerAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        if (OrchestratorFactory::getOrchestratorForInstance($this->instance)->stopComputing()) {
            $this->instance->setStatus(InstanceStatus::READY);
            $this->persistInstance();

            return $this->render();
        }

        return $this->render(['message' => ':('], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/start", methods={"PUT"}, name="instance.start")
     */
    public function startInstanceAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        if (OrchestratorFactory::getOrchestratorForInstance($this->instance)->startComputing()) {
            $this->instance->setStatus(InstanceStatus::RUNNING);
            $this->persistInstance();

            return $this->render(['message' => 'worked!']);
        }

        return $this->render(['message' => ':('], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/remove", methods={"DELETE"}, name="instance.remove")
     */
    public function removeInstanceAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $this->instance->setHidden(true);
        $this->persistInstance();

        return $this->render(['success' => true, 'removed' => true]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/cleanup", methods={"DELETE"}, name="instance.cleanup")
     */
    public function cleanupInstanceAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        if (OrchestratorFactory::getOrchestratorForInstance($this->instance)->stopComputing() && OrchestratorFactory::getOrchestratorForInstance($this->instance)->removeInstance()) {
            $this->instance->setHidden(true);
            $this->persistInstance();

            return $this->render(['success' => true, 'removed' => true]);
        }

        return $this->render(['message' => ':('], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/togglefree", methods={"PUT"}, name="instance.remove")
     */
    public function toggleFreeComputingAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $this->instance->setAllowFreeComputing(!$this->instance->isAllowFreeComputing());
        $this->persistInstance();

        return $this->render(['success' => true, 'message' => 'Free computing is now ' . $this->instance->isAllowFreeComputing() ? 'on' : 'off']);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("", methods={"POST"}, name="instance.add")
     */
    public function addInstanceAction(): ResponseInterface
    {
        try {
            $this->requiredParameterCheck([
                'fqdn' => FILTER_SANITIZE_STRING,
                'instancetype' => FILTER_SANITIZE_STRING,
            ]);
        } catch (RuntimeException $e) {
            if ('___NEW' === $this->instance->getName() && InstanceStatus::UNKNOWN === $this->instance->getStatus()) {
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
            ->setStatus(InstanceStatus::INIT);

        $this->optionalParameterCheck([
            'instancename' => FILTER_SANITIZE_STRING,
            'instanelocation' => FILTER_SANITIZE_STRING,
            'instancelevel' => FILTER_SANITIZE_STRING,
            'provision' => FILTER_SANITIZE_STRING,
            'openstackEndpoint' => FILTER_SANITIZE_STRING,
            'openstackAuth' => FILTER_SANITIZE_STRING,
            'bearcloudToken' => FILTER_SANITIZE_STRING,
            'billingReference' => FILTER_SANITIZE_STRING,
        ]);

        $this->instance
            ->setName($this->params['instancename'] ?? 'Automatically named during creation')
            ->setRegion($this->params['instancelocation'] ?? '')
            ->setSecurity($this->params['instancelevel'] ?? '')
            ->setBillingReference($this->params['billingReference'] ?? '');

        if (InstanceType::BEARCLOUD === $this->params['instancetype']) {
            $this->instance->setSupervisorToken($this->params['bearcloudToken'] ?? '');
        }
        if (InstanceType::OPENSTACK === $this->params['instancetype']) {
            $this->instance
                ->setSupervisorToken($this->params['openstackAuth'] ?? '')
                ->setSupervisorApi($this->params['openstackEndpoint']);
        }

        if (!array_key_exists('allowFree', $this->params) || 'on' !== $this->params['allowFree']) {
            $this->instance->setAllowFreeComputing(false);
        }

        if (null === $this->instance->getId()) {
            $this->instance
                ->setOwner($this->user)
                ->setCreated();
        }

        if (array_key_exists('provision', $this->params) && 'on' === $this->params['provision'] && InstanceFactory::getInstanceForServer($this->instance)->provisionInstance()) {
            $this->persistInstance();
            $this->instance->setStatus(InstanceStatus::CREATED);
        }

        $this->persistInstance();

        return $this->render([
            'html' => $this->fetchPartial('listItemInstance', ['instance' => $this->instance, 'user' => $this->user]),
            'message' => 'Instance <strong>' . $this->instance->getName() . '</strong> added.',
            'id' => $this->instance->getId(),
            'token' => JwtUtility::generateToken(null, $this->user, $this->instance)['token'],
        ]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/add/abort", methods={"POST"}, name="server.abort")
     *
     * @throws Exception
     */
    public function abortAddInstanceAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        if ($this->instance && InstanceStatus::UNKNOWN === $this->instance->getStatus() && $this->instance->getOwner() && $this->instance->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeInstance($this->instance);
            App::getDbHelper()->remove($this->instance);
            App::getDbHelper()->flush($this->instance);
            $this->persistUser();

            return $this->render();
        }

        return $this->render(['message' => 'no access to server'], StatusCode::HTTP_UNAUTHORIZED);
    }

    /**
     * Get and Update Instance Status.
     *
     * Hint: This method might be called recursively if the status changes.
     *
     * @return ResponseInterface
     * @Route("/status", methods={"GET"}, name="server.status")
     *
     * @throws Exception
     */
    public function getStatusAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $status = $this->ensureAndGetInstanceStatus();

        $data = [
            'status' => $status,
            'instance' => $this->instance,
        ];

        if ($this->instance->getStatus() > InstanceStatus::CREATED) {
            if (empty($this->instance->getInventory())) {
                OrchestratorFactory::getOrchestratorForInstance($this->instance)->getInventory();
            }
            $data['info'] = new InstanceInfoViewModel([$this->instance->getInventory(), $status]);
        }

        return $this->render(['listItemHtml' => $this->fetchPartial('listItemInstance', $data)]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/callback", methods={"POST", "GET"}, "name="instance.callback")
     */
    public function callbackAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $body = $this->request->getParsedBody();
        LogHelper::debug('Body received into instance ' . $this->instance->getId() . ' callback:' . print_r($body, true));

        $this->instance->setInventory($body);

        $this->persistInstance();

        return $this->render(['message' => 'ok']);
    }

    /**
     * @return ResponseInterface
     *
     * @throws GuzzleException
     * @throws Exception
     *
     * @Route("/metrics/snapshot/create", methods={"PUT", "GET"}, name="api.grafana.snapshot.create")
     */
    public function createSnapshotAction(): ResponseInterface
    {
        if (null === $this->instance->getId()) {
            return $this->render(['success' => false, 'message' => 'Instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        if ($json = $this->createSnapshot()) {
            $this->instance->setSnapshotConfig(json_encode($json));
            $this->persistInstance();

            return $this->render(['message' => 'created', 'snapshotUrl' => $json['url']]);
        }

        return $this->render(['status' => 'unknown'], StatusCode::HTTP_FAILED_DEPENDENCY);
    }
}
