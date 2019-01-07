<?php

namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;

interface MasterInterface
{
    public function __construct(Instance $server);

    public function getStatus(bool $returnInsteadOfCall = false);

    public function doSign(bool $returnInsteadOfCall = false);

    public function cleanup(bool $returnInsteadOfCall = false);
}