<?php
namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;

interface JobInterface {
    public function __construct(Job $job);

    public function run(array $params);
    public function stop(array $params);
}