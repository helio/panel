<?php

namespace Helio\Panel\Job;


use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use \Exception;
use \DateTime;
use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;


/**
 * Class AbstractExecute
 *
 * @package Helio\Panel\Job\Docker
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
     * @param Job $job
     * @param Execution|null $execution
     * @throws Exception
     */
    public function __construct(Job $job, Execution $execution = null)
    {
        $this->job = $job;
        $this->execution = $execution ?? new Execution();
    }


    /**
     * @param array $jobObject
     * @return bool
     * @throws Exception
     */
    public function create(array $jobObject): bool
    {


        $this->job->setName($jobObject['name'] ?? 'Automatically named during add')
            ->setCpus((int)($jobObject['cpus'] ?? 0))
            ->setGpus((int)($jobObject['gpus'] ?? 0))
            ->setLocation($jobObject['location'] ?? '')
            ->setBillingReference($jobObject['billingReference'] ?? '')
            ->setBudget((int)($jobObject['budget'] ?? 0))
            ->setIsCharity($jobObject['isCharity'] ?? '' === 'on')
            ->setConfig($jobObject['config'] ?? [])
            ->setStatus(JobStatus::INIT);
        App::getDbHelper()->persist($this->job);

        // set execution
        if ($this->execution->getId()) {
            $this->execution->setJob($this->job)->setEstimatedRuntime($this->calculateRuntime());
            App::getDbHelper()->persist($this->execution);
        }

        App::getDbHelper()->flush();
        return true;
    }


    /**
     * @param array $config
     * @return bool
     * @throws Exception
     */
    public function run(array $config): bool
    {

        $this->execution->setJob($this->job)->setCreated()->setStatus(ExecutionStatus::READY);
        App::getDbHelper()->persist($this->execution);
        App::getDbHelper()->flush();
        return true;
    }


    /**
     * @param array $config
     *
     * @return bool
     *
     * @throws Exception
     */
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


    /**
     * @param array $params
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws Exception
     */
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
                /** @var Response $response */
                return $response->withJson(array_merge(json_decode($lockedExecution->getConfig(), true), [
                    'id' => (string)$lockedExecution->getId(),
                ]), null, JSON_UNESCAPED_SLASHES);
            } catch (OptimisticLockException $e) {
                // trying next execution if the current one was modified in the meantime
            }
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }


    /**
     * @return array
     * @throws Exception
     */
    public function getExecutionEstimates(): array
    {
        $pendingExecutions = App::getDbHelper()->getRepository(Execution::class)->count(['status' => ExecutionStatus::READY, 'job' => $this->job]);

        // 0 means non-terminating worker
        if ($this->calculateRuntime() === 0) {
            return [
                'duration' => 'infinite',
                'completion' => 'never',
                'cost' => $this->job->getBudget() ?? 0 / $pendingExecutions
            ];
        }

        return [
            'duration' => $this->calculateRuntime(),
            'completion' => $this->calculateCompletion()->getTimestamp(),
            'cost' => $this->calculateCosts()
        ];
    }


    /**
     * @return int
     */
    abstract protected function calculateRuntime(): int;


    /**
     * @return DateTime
     * @throws Exception
     */
    protected function calculateCompletion(): DateTime
    {
        $pendingQuery = App::getDbHelper()->getRepository(Execution::class)->createQueryBuilder('e');
        $pendingQuery
            ->select('SUM(e.estimatedRuntime) as sum')
            ->join(Job::class, 'j')
            ->where($pendingQuery->expr()->andX()
                ->add($pendingQuery->expr()->gt($pendingQuery->expr()->length('j.autoExecSchedule'), 0))
                ->add($pendingQuery->expr()->eq('e.status', ExecutionStatus::READY))
                ->add($pendingQuery->expr()->eq('j.status', JobStatus::READY))
                ->add($pendingQuery->expr()->lte('e.priority', $this->execution->getPriority()))
                ->add($pendingQuery->expr()->lte('j.priority', $this->job->getPriority()))
            );
        $now = new DateTime('now', ServerUtility::getTimezoneObject());

        $duration = $pendingQuery->getQuery()->getArrayResult()[0]['sum'];

        return $now->setTimestamp($now->getTimestamp() + $duration);
    }


    /**
     * @return int
     */
    protected function calculateCosts(): int
    {
        return floor($this->execution->getEstimatedRuntime() * (4 * $this->job->getGpus()) * 10 / 60);
    }
}
