<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface JobInterface
 *
 * @package Helio\Panel\Job
 *
 */
interface JobInterface
{

    /**
     * JobInterface constructor.
     * @param Job $job
     */
    public function __construct(Job $job);

    /**
     * @param array $config
     *
     * @return bool
     */
    public function create(array $config): bool;


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
     * @param array $params
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function getnextinqueue(array $params, ResponseInterface $response): ResponseInterface;
}