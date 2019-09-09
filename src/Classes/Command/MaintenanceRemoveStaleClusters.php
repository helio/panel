<?php

namespace Helio\Panel\Command;

use DateTime;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Utility\NotificationUtility;
use Helio\Panel\Utility\ServerUtility;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceRemoveStaleClusters extends AbstractCommand
{
    /** @var string $defaultIdlePeriod DateInterval specifying how long a job cluster can remain idling */
    protected $defaultIdlePeriod = 'P1D';

    protected function configure(): void
    {
        $this->addArgument('maxIdlePeriod', InputArgument::OPTIONAL, 'DateInterval: How long shall a cluster remain idling before removal', $this->defaultIdlePeriod);
        $this->setName('app:maintenance-remove-stale-clusters')
            ->setDescription('Removing idle clusters that didn\'t run for a certain time.')
            ->setHelp('This task can be safely run every minute. It locks the execution internally.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $dummyInstance = (new Instance())->setName('___NEW')->setStatus(InstanceStatus::UNKNOWN);
        $now = new DateTime('now', ServerUtility::getTimezoneObject());
        try {
            $then = $now->sub(new \DateInterval($input->getArgument('maxIdlePeriod') ?: $this->defaultIdlePeriod));
        } catch (Exception $e) {
            $then = $now->sub(new \DateInterval($this->defaultIdlePeriod));
        }

        $query = App::getDbHelper()->getRepository(Job::class)->createQueryBuilder('j');
        $query->where($query->expr()->andX()
                    ->add($query->expr()->eq('j.status', JobStatus::READY))
                    ->add($query->expr()->eq($query->expr()->length('j.autoExecSchedule'), 0))
                    ->add($query->expr()->neq('j.persistent', 1))
            );

        $jobs = $query->getQuery()->getResult();
        if (empty($jobs)) {
            return 0;
        }

        /** @var Job $job */
        foreach ($jobs as $job) {
            try {
                /** @var Execution $newestRun */
                $newestRun = App::getDbHelper()->getRepository(Execution::class)->findOneBy(['job' => $job], ['created' => 'DESC']);
                if ($newestRun->getCreated() >= $then) {
                    continue;
                }

                App::getLogger()->debug('Cleaning up the cluster of job ' . $job->getId());

                $job->setStatus(JobStatus::READY_PAUSING);
                OrchestratorFactory::getOrchestratorForInstance($dummyInstance, $job)->removeManager();
                App::getDbHelper()->persist($job);
                App::getDbHelper()->flush();

                if (!$job->getOwner()->getPreferences()->getNotifications()->isMuteAdmin()) {
                    NotificationUtility::notifyAdmin('Cluster of Job ' . $job->getId() . ' is being removed due to too long idling time.');
                }
            } catch (Exception $e) {
                App::getLogger()->err(vsprintf('Error %s during cronjob cluster cleanup of job %s: %s', [$e->getCode(), $job->getId(), $e->getMessage()]));
                continue;
            }
        }

        return 0;
    }
}
