<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\AuthenticatedController;
use Helio\Panel\Controller\Traits\JobController;
use Helio\Panel\Controller\Traits\InstanceController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/precreate')
 *
 */
class PrecreateController extends AbstractController
{
    use AuthenticatedController;
    use InstanceController, JobController {
        InstanceController::setupParams insteadof JobController;
        InstanceController::requiredParameterCheck insteadof JobController;
        InstanceController::optionalParameterCheck insteadof JobController;
    }
    use TypeApiController;

    /**
     * @return ResponseInterface
     *
     * @Route("/instance", methods={"POST"}, name="instance.precreate")
     * @throws \Exception
     */
    public function precreateServerAction(): ResponseInterface
    {
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

        return $this->render(['instanceid' => $instance->getId(), 'token' => $instance->getToken()]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/instance/abort", methods={"POST"}, name="server.abort")
     * @throws \Exception
     */
    public function abortAddInstanceAction(): ResponseInterface
    {
        if ($this->instance->getStatus() === InstanceStatus::UNKNOWN && $this->instance->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeInstance($this->instance);
            $this->dbHelper->remove($this->instance);
            $this->dbHelper->flush($this->instance);
            $this->persistUser();
            return $this->render();
        }
        return $this->render(['message' => 'no access to server'], StatusCode::HTTP_UNAUTHORIZED);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/job", methods={"POST"}, name="job.precreate")
     * @throws \Exception
     */
    public function precreateJobAction(): ResponseInterface
    {
        $job = (new Job())
            ->setName('precreated automatically')
            ->setOwner($this->user)
            ->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
        $this->dbHelper->persist($job);
        $this->dbHelper->flush($job);

        $job->setToken(JwtUtility::generateJobIdentificationToken($job));

        $this->user->addJob($job);
        $this->dbHelper->persist($job);
        $this->dbHelper->flush($job);
        $this->persistUser();

        return $this->render(['jobid' => $job->getId(), 'token' => $job->getToken()]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/job/abort", methods={"POST"}, name="job.abort")
     * @throws \Exception
     */
    public function abortAddJobAction(): ResponseInterface
    {
        if ($this->job->getStatus() === JobStatus::UNKNOWN && $this->job->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeJob($this->job);
            $this->dbHelper->remove($this->job);
            $this->dbHelper->flush($this->job);
            $this->persistUser();
            return $this->render();
        }
        return $this->render(['message' => 'no access to job'], StatusCode::HTTP_UNAUTHORIZED);
    }

}