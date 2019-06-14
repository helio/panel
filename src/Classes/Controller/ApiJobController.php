<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\InstanceController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\AuthorizedJobController;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Task;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\MailUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;


/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/job')
 *
 *
 */
class ApiJobController extends AbstractController
{
    use AuthorizedJobController, InstanceController {
        AuthorizedJobController::setupParams insteadof InstanceController;
        AuthorizedJobController::requiredParameterCheck insteadof InstanceController;
        AuthorizedJobController::optionalParameterCheck insteadof InstanceController;
    }

    use TypeDynamicController;

    /**
     * @return ResponseInterface
     *
     * @Route("/remove", methods={"DELETE"}, name="job.remove")
     *
     */
    public function removeJobAction(): ResponseInterface
    {
        /** @var Task $task */
        JobFactory::getInstanceOfJob($this->job)->stop($this->params, $this->request, $this->response);

        // first: set all services to absent. then, remove the managers
        OrchestratorFactory::getOrchestratorForInstance($this->instance)->dispatchJob($this->job);
        OrchestratorFactory::getOrchestratorForInstance($this->instance)->removeManager($this->job);

        $this->persistJob();

        return $this->render(['success' => true]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/add", methods={"POST"}, name="job.add")
     * @throws \Exception
     */
    public function addJobAction(): ResponseInterface
    {
        try {
            $this->requiredParameterCheck([
                'jobtype' => FILTER_SANITIZE_STRING
            ]);

            if (!JobType::isValidType($this->params['jobtype'])) {
                return $this->render(['success' => false, 'message' => 'Unknown Job Type'], StatusCode::HTTP_METHOD_NOT_ALLOWED);
            }

            $this->job->setType($this->params['jobtype']);
        } catch (\Exception $e) {
            // If we have created a new job but haven't passed the jobtype (e.g. during wizard loading), we cannot continue.
            if (($this->params['jobid'] ?? '') === '_NEW') {
                $this->job->setToken(JwtUtility::generateJobIdentificationToken($this->job));
                $this->persistJob();
                return $this->render(['token' => $this->job->getToken(), 'id' => $this->job->getId()]);
            }
            // if the existing job hasn't got a proper type, we cannot continue either, but that's a hard fail...
            if (!JobType::isValidType($this->job->getType())) {
                return $this->render(['success' => false, 'meassge' => $e->getMessage()], StatusCode::HTTP_NOT_ACCEPTABLE);
            }
        }

        $this->optionalParameterCheck([
            'jobname' => FILTER_SANITIZE_STRING,
            'cpus' => FILTER_SANITIZE_STRING,
            'gpus' => FILTER_SANITIZE_STRING,
            'location' => FILTER_SANITIZE_STRING,
            'billingReference' => FILTER_SANITIZE_STRING,
            'budget' => FILTER_SANITIZE_STRING,
            'free' => FILTER_SANITIZE_STRING
        ]);

        $this->job->setName($this->params['jobname'] ?? 'Automatically named during add')
            ->setStatus(JobStatus::INIT)
            ->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))
            ->setToken(JwtUtility::generateJobIdentificationToken($this->job))
            ->setOwner($this->user)
            ->setCpus($this->params['cpus'] ?? '')
            ->setGpus($this->params['gpus'] ?? '')
            ->setLocation($this->params['location'] ?? '')
            ->setBillingReference($this->params['billingReference'] ?? '')
            ->setBudget($this->params['budget'] ?? '')
            ->setIsCharity($this->params['free'] ?? '' === 'on');

        JobFactory::getInstanceOfJob($this->job)->create($this->params, $this->request);

        $this->persistJob();

        MailUtility::sendMailToAdmin('New Job was created by ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());

        OrchestratorFactory::getOrchestratorForInstance($this->instance)->provisionManager($this->job);

        return $this->render([
            'success' => true,
            'token' => $this->job->getToken(),
            'id' => $this->job->getId(),
            'html' => $this->fetchPartial('listItemJob', ['job' => $this->job, 'user' => $this->user]),
            'message' => 'Job <strong>' . $this->job->getName() . '</strong> added',
        ]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/add/abort", methods={"POST"}, name="job.abort")
     * @throws \Exception
     */
    public function abortAddJobAction(): ResponseInterface
    {
        if ($this->job && $this->job->getStatus() === JobStatus::UNKNOWN && $this->job->getOwner() && $this->job->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeJob($this->job);
            $this->dbHelper->remove($this->job);
            $this->dbHelper->flush($this->job);
            $this->persistUser();
            return $this->render();
        }
        return $this->render(['message' => 'no access to job'], StatusCode::HTTP_UNAUTHORIZED);
    }

    /**
     * @return ResponseInterface
     *
     * @OA\Get(
     *     path="/api/job/isready",
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job which status you wandt to see",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(response="200", description="Contains the Status")
     * ),
     *     security={
     *         {"authByJobtoken": {"any"}}
     *     }
     *
     * @Route("/isready", methods={"GET"}, name="exec.job.status")
     */
    public function jobIsReadyAction(): ResponseInterface
    {
        return $this->render([], $this->job->getStatus() === JobStatus::READY ? StatusCode::HTTP_OK : StatusCode::HTTP_FAILED_DEPENDENCY);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/callback", methods={"POST", "GET"}, "name="job.callback")
     *
     */
    public function callbackAction(): ResponseInterface
    {
        $body = $this->request->getParsedBody();
        LogHelper::debug('Body received into callback:' . print_r($body, true));

        // remember manager nodes.
        if (\array_key_exists('nodes', $body)) {
            $nodes = \is_array($body['nodes']) ? $body['nodes'] : array($body['nodes']);
            foreach ($nodes as $node) {

                if (\array_key_exists('deleted', $body)) {
                    $this->job->removeManagerNode($node);
                } else {
                    $this->job->addManagerNode($node);
                }
            }
        }

        // remember swarm token
        if (\array_key_exists('swarm_token_worker', $body)) {
            $this->job->setClusterToken($body['swarm_token_worker']);
        }
        if (\array_key_exists('swarm_token_manager', $body)) {
            $this->job->setManagerToken($body['swarm_token_manager']);
        }

        // get manager IP
        if (\array_key_exists('manager_ip', $body)) {
            $this->job->setInitManagerIp($body['manager_ip']);
        }

        $this->persistJob();

        // provision missing redundancy nodes if necessary
        if ($this->job->getInitManagerIp()) {
            OrchestratorFactory::getOrchestratorForInstance($this->instance)->provisionManager($this->job);
        }

        // finalize
        // TODO: set redundancy to >= 3 again if needed
        if ($this->job->getInitManagerIp() && $this->job->getClusterToken() && $this->job->getManagerToken() && \count($this->job->getManagerNodes()) > 0) {
            $this->job->setStatus(JobStatus::READY);
            MailUtility::sendMailToAdmin('Job is now read. By: ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());
        }
        if (\array_key_exists('deleted', $body) && \count($this->job->getManagerNodes()) === 0) {
            $this->job->setStatus(JobStatus::DELETED);
            MailUtility::sendMailToAdmin('Job was deleted by ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());
        }

        $this->persistJob();
        return $this->render(['message' => 'ok']);

    }


    /**
     * @return ResponseInterface
     *
     * @Route("/manager/init", methods={"GET"}, name="job.manager.init")
     */
    public function getInitManagerNodeConfigAction(): ResponseInterface
    {
        $config = [
            'classes' => ['role::base', 'profile::docker'],
            'profile::docker::manager' => true,
            'profile::docker::manager_init' => true
        ];
        return $this
            ->setReturnType('yaml')
            ->render($config);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/manager/redundancy", methods={"GET"}, name="job.manager.redundancy")
     */
    public function getRedundantManagerNodeConfigAction(): ResponseInterface
    {
        $config = [
            'classes' => ['role::base', 'profile::docker'],
            'profile::docker::manager' => true,
            'profile::docker::manager_init' => true,
            'profile::docker::manager_ip' => $this->job->getInitManagerIp(),
            'profile::docker::token' => $this->job->getClusterToken()
        ];
        return $this
            ->setReturnType('yaml')
            ->render($config);
    }
}