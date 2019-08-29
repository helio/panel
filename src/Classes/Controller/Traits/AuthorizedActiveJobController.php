<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Exception\HttpException;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Slim\Http\StatusCode;

trait AuthorizedActiveJobController
{
    use AuthorizedJobController;

    public function validateJobIsActive(): void
    {
        if (!$this->job) {
            throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'No job found');
        }
        if (JobType::isValidType($this->job->getType()) && JobStatus::isValidActiveStatus($this->job->getStatus())) {
            return;
        }
        throw new HttpException(StatusCode::HTTP_FORBIDDEN, 'Job is not active');
    }
}
