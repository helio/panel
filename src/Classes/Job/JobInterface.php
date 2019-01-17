<?php
namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface JobInterface {
    public function __construct(Job $job);

    public function run(array $params, RequestInterface $request, ResponseInterface $response);

    public function stop(array $params);

    public function create(array $params): bool;
}