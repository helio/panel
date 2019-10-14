<?php

namespace Helio\Panel\Command;

use Ahc\Cron\Expression;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecuteScheduledJob extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('app:execute-scheduled-jobs')
            ->setDescription('Checks all Jobs for due scheduled executions and runs them if necessary.')
            ->setHelp('This task should run as often as possible through a cron.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $expression = (new ExpressionBuilder())->neq('autoExecSchedule', '');

        $jobs = App::getDbHelper()->getRepository(Job::class)->matching(new Criteria($expression));

        /** @var Job $job */
        foreach ($jobs as $job) {
            try {
                if (Expression::isDue($job->getAutoExecSchedule()) && JobStatus::isValidActiveStatus($job->getStatus())) {
                    App::getLogger()->debug('Running Scheduled Job ' . $job->getId());

                    /** @var Execution $execution */
                    $pseudoInstance = new Instance();
                    $execution = (new Execution())
                        ->setStatus(ExecutionStatus::READY)
                        ->setJob($job)
                        ->setAutoExecuted(true)
                        ->setCreated()
                        ->setName('created by CLI');

                    // run the job and check if the replicas have changed
                    $previousReplicaCount = JobFactory::getDispatchConfigOfJob($job, $execution)->getDispatchConfig()->getReplicaCountForJob($job);
                    JobFactory::getInstanceOfJob($job, $execution)->run($job->getConfig('cliparams', []));
                    $newReplicaCount = JobFactory::getDispatchConfigOfJob($job, $execution)->getDispatchConfig()->getReplicaCountForJob($job);

                    // if replica count has changed OR we have an enforcement (e.g. one replica per execution fixed), dispatch the job
                    if ($previousReplicaCount !== $newReplicaCount || JobFactory::getDispatchConfigOfJob($job, $execution)->getDispatchConfig()->getFixedReplicaCount()) {
                        App::getLogger()->debug('Dispatching execution ' . $execution->getId() . 'for cron-run job ' . $job->getId());
                        OrchestratorFactory::getOrchestratorForInstance($pseudoInstance, $job)->dispatchJob();
                        App::getDbHelper()->persist($job);
                        App::getDbHelper()->persist($execution);
                        App::getDbHelper()->flush();
                    }

                    if (!$job->getOwner()->getPreferences()->getNotifications()->isMuteAdmin()) {
                        App::getNotificationUtility()::notifyAdmin('New execution for job ' . $job->getId() . ' automatically created');
                    }
                }
            } catch (Exception $e) {
                App::getLogger()->err('Warning ' . $e->getCode() . ' during cronjob job init: ' . $e->getMessage());
                continue;
            }
        }

        return 0;
    }
}
