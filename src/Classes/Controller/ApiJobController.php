<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\InstanceController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\AuthorizedJobController;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
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
 * @OA\Info(title="Job API", version="0.0.1")
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
     * @OA\Delete(
     *     path="/api/job/remove",
     *     @OA\Response(response="200", description="Job has been deleted")
     * )
     */
    public function removeJobAction(): ResponseInterface
    {
        $this->job->setHidden(true);
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

        MailUtility::sendMailToAdmin('New Job was created by ' . $this->user->getEmail() . 'type: ' . $this->job->getType() . ', id: ' . $this->job->getId());

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
     */
    public function callbackAction(): ResponseInterface
    {
        $result = false;

        // have to get init manager node ip
        if (!$this->job->getInitManagerIp() && \count($this->job->getManagerNodes()) === 1) {
            $result = OrchestratorFactory::getOrchestratorForInstance($this->instance)->setInitManagerNodeIp($this->job)
                && OrchestratorFactory::getOrchestratorForInstance($this->instance)->setClusterToken($this->job);
        }

        // provision missing redundancy nodes
        if ($this->job->getInitManagerIp() && \count($this->job->getManagerNodes()) < 3) {
            $result = OrchestratorFactory::getOrchestratorForInstance($this->instance)->provisionManager($this->job);
        }

        //
        if ($result && \count($this->job->getManagerNodes()) === 3) {
            $this->job->setStatus(JobStatus::READY);
            $this->persistJob();
            return $this->render(['message' => 'ok']);
        }

        return $this->render(['message' => 'unknown error', StatusCode::HTTP_INTERNAL_SERVER_ERROR]);

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