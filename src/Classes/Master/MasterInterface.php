<?php

namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface MasterInterface
{
    public function __construct(Instance $instance);

    public function getStatus(bool $returnInsteadOfCall = false);

    public function doSign(bool $returnInsteadOfCall = false);

    public function cleanup(bool $returnInsteadOfCall = false);

    public function dispatchJob(Job $job, bool $returnInsteadOfCall = false): bool;
}