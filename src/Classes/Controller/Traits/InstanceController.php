<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;

/**
 * Trait ServerController
 * @package Helio\Panel\Controller\Traits
 * @method User getUser()
 * @method bool hasUser()
 */
trait InstanceController
{
    use ParametrizedController;

    /**
     * @var Instance
     */
    protected $instance;


    /**
     * @return bool
     */
    public function setupInstance(): bool
    {
        $this->setupParams();
        $instanceId = filter_var($this->params['instanceid'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        if ($instanceId === 0) {
            return false;
        }
        $this->instance = $this->dbHelper->getRepository(Instance::class)->find($instanceId);
        return true;
    }

    /**
     * Persist
     */
    protected function persistInstance(): void {
        $this->dbHelper->persist($this->instance);
        $this->dbHelper->flush($this->instance);
    }
}