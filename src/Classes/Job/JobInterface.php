<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
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
     * @param array             $params
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function getnextinqueue(array $params, ResponseInterface $response): ResponseInterface;
}
