<?php

namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;

interface MasterInterface
{
    public function __construct(Instance $server);

    public function getStatus(bool $returnInsteadOfCall = false): string;

    public function doSign(bool $returnInsteadOfCall = false): string;
}