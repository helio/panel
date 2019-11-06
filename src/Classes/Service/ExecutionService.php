<?php

namespace Helio\Panel\Service;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Repositories\ExecutionRepository;

class ExecutionService
{
    /**
     * @var ExecutionRepository
     */
    private $executionRepository;

    /**
     * @var DbHelper
     */
    private $dbHelper;

    public function __construct(ExecutionRepository $executionRepository, DbHelper $dbHelper = null)
    {
        $this->dbHelper = $dbHelper ?? App::getDbHelper();
        $this->executionRepository = $executionRepository;
    }

    public function setNextExecutionActive(Job $job): bool
    {
        $executions = $this->executionRepository->findExecutionsToStart($job);
        if (!count($executions)) {
            LogHelper::info('no executions to start found', ['job' => $job->getId()]);

            return false;
        }

        foreach ($executions as $execution) {
            try {
                /** @var Execution $lockedExecution */
                $lockedExecution = $this->executionRepository->find($execution->getId(), LockMode::OPTIMISTIC, $execution->getVersion());
                $lockedExecution->setReplicas(1);
                $this->dbHelper->persist($execution);
                $this->dbHelper->flush();

                // scale services accordingly
                OrchestratorFactory::getOrchestratorForInstance(new Instance(), $job)->dispatchReplicas([$lockedExecution]);

                return true;
            } catch (OptimisticLockException $e) {
                LogHelper::warn('exception when trying to lock execution', ['message' => $e->getMessage(), 'execution' => $execution->getId(), 'job' => $execution->getJob()->getId(), 'execution version' => $execution->getVersion()]);
                // trying next execution if the current one was modified in the meantime
            }
        }

        if (!empty($executions)) {
            LogHelper::warn('Executions that need scale-up found but not scaled up. Lock problem?', $executions);
        }

        return false;
    }
}
