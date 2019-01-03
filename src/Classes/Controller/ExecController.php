<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\AuthenticatedController;
use Helio\Panel\Controller\Traits\JobController;
use Helio\Panel\Controller\Traits\InstanceController;
use Helio\Panel\Controller\Traits\ParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
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
 * @RoutePrefix('/exec')
 *
 */
class ExecController extends AbstractController
{
    use JobController;
    use TypeApiController;


    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"POST", "PUT", "GET"}, name="job.exec")
     */
    public function execAction(): ResponseInterface {
        if (JobType::isValidType($this->job->getType())
            && JobStatus::isValidActiveStatus($this->job->getStatus())) {
            try {
                JobFactory::getInstanceOfJob($this->job)->run($this->params);
                return $this->render(['status' => 'success']);
            } catch (\Exception $e) {
                return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return $this->render(['status' => 'unknown'], StatusCode::HTTP_FAILED_DEPENDENCY);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"DELETE"}, name="job.stop")
     */
    public function stopAction(): ResponseInterface {
        if (JobType::isValidType($this->job->getType())
            && JobStatus::isValidActiveStatus($this->job->getStatus())) {
            try {
                JobFactory::getInstanceOfJob($this->job)->stop($this->params);
                return $this->render(['status' => 'success']);
            } catch (\Exception $e) {
                return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return $this->render(['status' => 'unknown'], StatusCode::HTTP_FAILED_DEPENDENCY);

    }
}