<?php

namespace Helio\Panel\Controller;


use \Exception;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\HelperElasticController;
use Helio\Panel\Controller\Traits\ModelInstanceController;
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
    use AuthorizedJobController, ModelInstanceController {
        AuthorizedJobController::setupParams insteadof ModelInstanceController;
        AuthorizedJobController::requiredParameterCheck insteadof ModelInstanceController;
        AuthorizedJobController::optionalParameterCheck insteadof ModelInstanceController;
    }

    use HelperElasticController;

    use TypeDynamicController;

    /**
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/remove", methods={"DELETE"}, name="job.remove")
     */
    public function removeJobAction(): ResponseInterface
    {
        $removed = false;
        if (!JobType::isValidType($this->job->getType())) {
            $this->job->setHidden(true);
            $removed = true;
        } else {
            /** @var Task $task */
            JobFactory::getInstanceOfJob($this->job)->stop($this->params, $this->request, $this->response);

            // first: set all services to absent. then, remove the managers
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->dispatchJob();
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->removeManager();
        }

        // on PROD, we wait for the callbacks to confirm job removal. on Dev, simply set it to deleted.
        if (!ServerUtility::isProd()) {
            $this->job->setStatus(JobStatus::DELETED);
            $removed = true;
        }

        $this->persistJob();

        return $this->render(['success' => true, 'message' => 'Job scheduled for removal.', 'removed' => $removed]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/add", methods={"POST"}, name="job.add")
     * @throws Exception
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
        } catch (Exception $e) {
            // If we have created a new job but haven't passed the jobtype (e.g. during wizard loading), we cannot continue.
            if ($this->job->getName() === '___NEW' && $this->job->getStatus() === JobStatus::UNKNOWN) {
                return $this->render(['token' => JwtUtility::generateToken(null, $this->user, null, $this->job)['token'], 'id' => $this->job->getId()]);
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
            ->setOwner($this->user)
            ->setCpus($this->params['cpus'] ?? '')
            ->setGpus($this->params['gpus'] ?? '')
            ->setLocation($this->params['location'] ?? '')
            ->setBillingReference($this->params['billingReference'] ?? '')
            ->setBudget($this->params['budget'] ?? '')
            ->setIsCharity($this->params['free'] ?? '' === 'on')
            ->setStatus(JobStatus::INIT);

        JobFactory::getInstanceOfJob($this->job)->create($this->params, $this->request);

        $this->persistJob();

        MailUtility::sendMailToAdmin('New Job was created by ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());

        OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->provisionManager();

        return $this->render([
            'success' => true,
            'token' => JwtUtility::generateToken(null, $this->user, null, $this->job)['token'],
            'id' => $this->job->getId(),
            'html' => $this->fetchPartial('listItemJob', ['job' => $this->job, 'user' => $this->user]),
            'message' => 'Job <strong>' . $this->job->getName() . '</strong> added',
        ]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/add/abort", methods={"POST"}, name="job.abort")
     * @throws Exception
     */
    public function abortAddJobAction(): ResponseInterface
    {
        if ($this->job && $this->job->getStatus() === JobStatus::UNKNOWN && $this->job->getOwner() && $this->job->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeJob($this->job);
            App::getDbHelper()->remove($this->job);
            App::getDbHelper()->flush();
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
        $ready = $this->job->getStatus() === JobStatus::READY;
        $status = $ready ? StatusCode::HTTP_OK : StatusCode::HTTP_FAILED_DEPENDENCY;
        $message = $ready ? 'Job is ready' : 'Execution environment for job is being prepared...';

        return $this->render(['success' => true, 'message' => $message], $status);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/status", methods={"GET"}, name="exec.job.status")
     */
    public function jobStatusAction(): ResponseInterface
    {
        return $this->render(['success' => true, 'status' => JobStatus::getLabel($this->job->getStatus())]);
    }


    /**
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/logs", methods={"GET"}, name="job.logs")
     */
    public function logsAction(): ResponseInterface
    {
        if (!$this->job->getOwner()) {
            return $this->render([]);
        }
        return $this->render($this->setWindow()->getLogEntries($this->job->getOwner()->getId(), $this->job->getId()));
    }


    /**
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/callback", methods={"POST", "GET"}, "name="job.callback")
     */
    public function callbackAction(): ResponseInterface
    {
        $body = $this->request->getParsedBody();
        LogHelper::debug('Body received into job ' . $this->job->getId() . ' callback:' . print_r($body, true));

        // remember manager nodes.
        if (array_key_exists('nodes', $body)) {
            $nodes = is_array($body['nodes']) ? $body['nodes'] : array($body['nodes']);
            foreach ($nodes as $node) {
                if (array_key_exists('deleted', $body)) {
                    $this->job->removeManagerNode($node);
                } else {
                    $this->job->addManagerNode($node);
                }
            }
        }

        // remember swarm token
        if (array_key_exists('swarm_token_worker', $body)) {
            $this->job->setClusterToken($body['swarm_token_worker']);
        }
        if (array_key_exists('swarm_token_manager', $body)) {
            $this->job->setManagerToken($body['swarm_token_manager']);
        }

        // get manager IP
        if (array_key_exists('manager_ip', $body)) {
            $this->job->setInitManagerIp($body['manager_ip']);
        }

        $this->persistJob();

        // provision missing redundancy nodes if necessary
        if (!array_key_exists('deleted', $body) && $this->job->getInitManagerIp()) {
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->provisionManager();
        }

        // finalize
        // TODO: set redundancy to >= 3 again if needed
        if ($this->job->getInitManagerIp() && $this->job->getClusterToken() && $this->job->getManagerToken() && count($this->job->getManagerNodes()) > 0) {
            $this->job->setStatus(JobStatus::READY);
            MailUtility::sendMailToAdmin('Job is now read. By: ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());
        }
        if (array_key_exists('deleted', $body) && count($this->job->getManagerNodes()) === 0) {
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