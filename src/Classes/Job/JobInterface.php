<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;
use Psr\Http\Message\RequestInterface;
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
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool;


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return mixed
     */
    public function stop(array $params, RequestInterface $request, ResponseInterface $response = null);


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface $response
     *
     * @return mixed
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response);
}