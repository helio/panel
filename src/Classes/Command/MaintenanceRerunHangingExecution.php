<?php

namespace Helio\Panel\Command;

use Exception;
use Doctrine\DBAL\Types\Type;
use Helio\Panel\App;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Utility\ServerUtility;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceRerunHangingExecution extends AbstractCommand
{
    /** @var string $gracePeriod DateInterval specifying how long we should wait before retry */
    protected $defaultGracePeriod = 'PT5H';

    protected function configure(): void
    {
        $this->addArgument('gracePeriod', InputArgument::OPTIONAL, 'DateInterval: How long the command should wait before rerunning', $this->defaultGracePeriod);
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Just fetch executions and print them, do not run commands');
        $this->setName('app:maintenance-rerun-hanging-executions')
            ->setDescription('Runs a hanging execution again.')
            ->setHelp('This task can be safely run every minute. It locks the execution internally.');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $dbHelper = App::getDbHelper();

        $dummyInstance = (new Instance())->setName('___NEW')->setStatus(InstanceStatus::UNKNOWN);
        $now = new \DateTime('now', ServerUtility::getTimezoneObject());
        try {
            $then = $now->sub(new \DateInterval($input->getArgument('gracePeriod') ?: $this->defaultGracePeriod));
        } catch (\Exception $e) {
            $then = $now->sub(new \DateInterval($this->defaultGracePeriod));
        }

        $executions = $this->fetchHangingExpressions($dbHelper, $then);
        if (empty($executions)) {
            return 0;
        }

        $logger = App::getLogger();
        $dispatchedJobs = [];

        /** @var Execution $execution */
        foreach ($executions as $execution) {
            try {
                $job = $execution->getJob();
                $logger->info('Rerunning hanging execution', [
                    'id' => $execution->getId(),
                    'job' => $job->getId(),
                    'execution status' => ExecutionStatus::getLabel($execution->getStatus()),
                    'job status' => JobStatus::getLabel($job->getStatus()),
                ]);

                if ($input->getOption('dry-run')) {
                    $logger->info('dry-run - not running anything');
                    continue;
                }

                $execution->resetStarted()->resetLatestHeartbeat()->setStatus(ExecutionStatus::READY);

                if (!isset($dispatchedJobs[$job->getId()])) {
                    OrchestratorFactory::getOrchestratorForInstance($dummyInstance, $job)->dispatchJob();
                    $dispatchedJobs[$job->getId()] = true;
                }

                if (!$job->getOwner()->getPreferences()->getNotifications()->isMuteAdmin()) {
                    App::getNotificationUtility()::notifyAdmin('Hanging execution ' . $execution->getId() . ' has been reset');
                }

                $dbHelper->persist($execution);
                $dbHelper->persist($job);
            } catch (\Exception $e) {
                $logger->err('Warning ' . $e->getCode() . ' during cronjob job init: ' . $e->getMessage());
                continue;
            }
        }
        $dbHelper->flush();

        return 0;
    }

    /**
     * Fetches list of executions
     *  - which are associated to a running job
     *  - and are either running with a heartbeat older than `$then`
     *  - or are ready and created older than `$then`.
     *
     * TODO(mw): I'm pretty sure this query could be simplified. What's weird is that doctrine doesn't
     *           allow to use `on` for the job = execution job id relation.
     *
     * @param DbHelper $dbHelper
     * @param $then
     * @return array
     */
    protected function fetchHangingExpressions(DbHelper $dbHelper, \DateTime $then): array
    {
        $query = $dbHelper->getRepository(Execution::class)->createQueryBuilder('e');
        $query
            ->join(Job::class, 'j')
            ->where(
                $query->expr()->andX()
                    ->add($query->expr()->eq('e.job', 'j.id'))
                    ->add($query->expr()->in('j.status', JobStatus::getRunningStatusCodes()))
                    ->add(
                        $query->expr()->orX()
                            ->add(
                                $query->expr()->andX()
                                    ->add($query->expr()->eq('e.status', ExecutionStatus::RUNNING))
                                    ->add($query->expr()->isNotNull('e.latestHeartbeat'))
                                    ->add($query->expr()->lt('e.latestHeartbeat', ':then'))
                            )
                            ->add(
                                $query->expr()->andX()
                                    ->add($query->expr()->eq('e.status', ExecutionStatus::READY))
                                    ->add($query->expr()->lt('e.created', ':then'))
                            )
                    )
            )
            ->setParameter('then', $then, Type::DATETIME);

        return $query->getQuery()->getResult();
    }
}
