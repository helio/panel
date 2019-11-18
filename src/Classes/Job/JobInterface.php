<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface JobInterface.
 */
interface JobInterface
{
    /**
     * JobInterface constructor.
     * @param Job            $job
     * @param Execution|null $execution
     */
    public function __construct(Job $job, Execution $execution = null);

    /**
     * Validates the passed job object on a per-job-type basis.
     *
     * If return value is null, there has been no error and it's ok to continue.
     * If an array is returned this will contain a list of error messages.
     *
     * @param  User       $user
     * @param  array      $jobObject
     * @return array|null
     */
    public function validate(User $user, array $jobObject): ?array;

    /**
     * @param array $jobObject
     *
     * @return bool
     */
    public function create(array $jobObject): bool;

    /**
     * @param array $config
     *
     * @return mixed
     */
    public function stop(array $config);

    /**
     * @param array $config
     *
     * @return mixed
     */
    public function run(array $config);

    /**
     * Call when an execution is finshed. Will throw if no exection was passed to constructor.
     *
     * @param  string $stats
     * @return mixed
     */
    public function executionDone(string $stats);

    /**
     * @param array             $params
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function getnextinqueue(array $params, ResponseInterface $response): ResponseInterface;
}
