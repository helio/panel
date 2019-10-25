<?php

namespace Helio\Panel\Job;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use DateTime;
use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * Class AbstractExecute.
 */
abstract class AbstractExecute implements JobInterface, DispatchableInterface
{
    /**
     * @var Job
     */
    protected $job;
    /**
     * @var Execution
     */
    protected $execution;

    /**
     * Execute constructor.
     *
     * @param Job            $job
     * @param Execution|null $execution
     *
     * @throws Exception
     */
    public function __construct(Job $job, Execution $execution = null)
    {
        $this->job = $job;
        $this->execution = $execution ?? new Execution();
    }

    /**
     * @param array $jobObject
     *
     * @return bool
     *
     * @throws Exception
     */
    public function create(array $jobObject): bool
    {
        $this->job->setName($jobObject['name'] ?? 'Automatically named during add')
            ->setCpus((int) ($jobObject['cpus'] ?? 0))
            ->setGpus((int) ($jobObject['gpus'] ?? 0))
            ->setLocation($jobObject['location'] ?? '')
            ->setBillingReference($jobObject['billingReference'] ?? '')
            ->setBudget((int) ($jobObject['budget'] ?? 0))
            ->setIsCharity($jobObject['isCharity'] ?? '' === 'on')
            ->setPersistent($jobObject['persistent'] ?? '' === 'on')
            ->setConfig($jobObject['config'] ?? '')
            ->setAutoExecSchedule($jobObject['autoExecSchedule'] ?? '')
            ->setLabels($jobObject['labels'] ?? []);

        // only set status if not set yet. If a job is updated the status should not change.
        if (JobStatus::UNKNOWN === $this->job->getStatus()) {
            $this->job->setStatus(JobStatus::INIT)->setCreated();
        }

        // set execution
        if (null !== $this->execution->getId()) {
            $this->execution->setJob($this->job)->setEstimatedRuntime($this->calculateRuntime());
            App::getDbHelper()->persist($this->execution);
        }

        App::getDbHelper()->persist($this->job);
        App::getDbHelper()->flush($this->job);

        return true;
    }

    /**
     * @param array $config
     *
     * @return bool
     *
     * @throws Exception
     */
    public function run(array $config): bool
    {
        $this->execution->setJob($this->job)->setConfig($config)->setCreated()->setStatus(ExecutionStatus::READY);
        $this->execution->setEstimatedRuntime($this->calculateRuntime());
        App::getDbHelper()->persist($this->execution);
        App::getDbHelper()->flush();

        return true;
    }

    public function stop(array $config): bool
    {
        $executions = $this->execution ? [$this->execution] : DbHelper::getInstance()->getRepository(Execution::class)->findBy(['job' => $this->job]);
        /** @var Execution $execution */
        foreach ($executions as $execution) {
            if (ExecutionStatus::isValidPendingStatus($execution->getStatus() || ExecutionStatus::isRunning($execution->getStatus()))) {
                $execution->setStatus(ExecutionStatus::TERMINATED);
                App::getDbHelper()->persist($execution);
            }
        }
        App::getDbHelper()->flush();

        return true;
    }

    public function getnextinqueue(array $params, ResponseInterface $response): ResponseInterface
    {
        $executions = App::getDbHelper()->getRepository(Execution::class)->findBy(['job' => $this->job, 'status' => ExecutionStatus::READY], ['priority' => 'ASC', 'created' => 'ASC'], 5);
        /** @var Execution $execution */
        foreach ($executions as $execution) {
            try {
                /** @var Execution $lockedExecution */
                $lockedExecution = App::getDbHelper()->getRepository(Execution::class)->find($execution->getId(), LockMode::OPTIMISTIC, $execution->getVersion());
                $lockedExecution->setStatus(ExecutionStatus::RUNNING);
                App::getDbHelper()->flush();
                /* @var Response $response */
                return $response->withJson(array_merge(json_decode($lockedExecution->getConfig(), true), [
                    'id' => (string) $lockedExecution->getId(),
                ]), null, JSON_UNESCAPED_SLASHES);
            } catch (OptimisticLockException $e) {
                // trying next execution if the current one was modified in the meantime
            }
        }

        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }

    public function getExecutionEstimates(): array
    {
        $pendingExecutions = App::getDbHelper()->getRepository(Execution::class)->count(['status' => ExecutionStatus::READY, 'job' => $this->job]);

        // 0 means non-terminating worker
        if (0 === $this->calculateRuntime()) {
            return [
                'duration' => 'infinite',
                'completion' => 'never',
                'cost' => $this->job->getBudget() ?? 0 / $pendingExecutions,
            ];
        }

        return [
            'duration' => $this->calculateRuntime(),
            'completion' => $this->calculateCompletion()->getTimestamp(),
            'cost' => $this->calculateCosts(),
        ];
    }

    public function isExecutionStillAffordable(): bool
    {
        if ($this->job->getBudget() > 0) {
            // current execution would burst the budget
            if ($this->calculateCosts() + $this->job->getBudgetUsed() > $this->job->getBudget()) {
                return false;
            }

            // all pending executions would burst the budget
            // Note: This is not production ready since not all executions cost the same, so it's just an assumption here to multiply them.
            if ($this->job->getActiveExecutionCount() * $this->calculateCosts() + $this->job->getBudgetUsed() > $this->job->getBudget()) {
                return false;
            }
        }

        return true;
    }

    abstract protected function calculateRuntime(): int;

    protected function calculateCompletion(): DateTime
    {
        $pendingQuery = App::getDbHelper()->getRepository(Execution::class)->createQueryBuilder('e');
        $pendingQuery
            ->select('SUM(e.estimatedRuntime) as sum')
            ->join(Job::class, 'j')
            ->where(
                $pendingQuery->expr()->andX()
                    ->add($pendingQuery->expr()->eq('j.id', $this->job->getId()))
                    ->add($pendingQuery->expr()->eq($pendingQuery->expr()->length('j.autoExecSchedule'), 0))
                    ->add($pendingQuery->expr()->eq('e.status', ExecutionStatus::READY))
                    ->add($pendingQuery->expr()->eq('j.status', JobStatus::READY))
                    ->add($pendingQuery->expr()->lte('e.priority', $this->execution->getPriority()))
                    ->add($pendingQuery->expr()->lte('j.priority', $this->job->getPriority()))
            );
        $now = new DateTime('now', ServerUtility::getTimezoneObject());

        $duration = $pendingQuery->getQuery()->getArrayResult()[0]['sum'];

        return $now->setTimestamp($now->getTimestamp() + $duration);
    }

    protected function calculateCosts(): float
    {
        //      seconds of runtime                        make it hours         gpus are 10x more expensive than cpus                 CPU $ per Hour
        return ($this->execution->getEstimatedRuntime() / 3600) * (10 * ($this->job->getGpus() ?: 1)) * ($this->job->getCpus() ?: 1) * 0.01;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getCommonEnvVariables(): array
    {
        return array_merge($this->job->getConfig('env', []), [
            'HELIO_JOBID' => $this->job->getId(),
            'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
            'STATUS_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl($this->job, '', $this->execution),
            'REPORT_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl($this->job, 'submitresult', $this->execution),
            'HEARTBEAT_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl($this->job, 'heartbeat', $this->execution),
        ]);
    }
}
