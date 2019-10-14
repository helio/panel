<?php

namespace Helio\Panel\Command;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Utility\ServerUtility;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceRedeployHangingJobs extends AbstractCommand
{
    /** @var string $gracePeriod DateInterval specifying how long we should wait before retry */
    protected $defaultGracePeriod = 'PT5M';

    protected function configure(): void
    {
        $this->addArgument('gracePeriod', InputArgument::OPTIONAL, 'DateInterval: How long the command should wait befor redeploying', $this->defaultGracePeriod);
        $this->setName('app:maintenance-redeploy-hanging-jobs')
            ->setDescription('Tries to setup the managers of hanging jobs again.')
            ->setHelp('This task can be safely run every minute. It locks the jobs internally.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $expression = (new ExpressionBuilder())->in('status', [JobStatus::INIT_ERROR, JobStatus::DELETING_ERROR]);

        $jobs = App::getDbHelper()->getRepository(Job::class)->matching(new Criteria($expression));
        $dummyInstance = (new Instance())->setName('___NEW')->setStatus(InstanceStatus::UNKNOWN);

        if ($jobs->isEmpty()) {
            return 0;
        }

        /** @var Job $job */
        foreach ($jobs as $job) {
            try {
                App::getLogger()->debug('Trying to re-deploy hanging job with ID ' . $job->getId());

                if ($this->waitingPeriodOver($job, $input->getArgument('gracePeriod'))) {
                    switch ($job->getStatus()) {
                        case JobStatus::INIT_ERROR:
                            OrchestratorFactory::getOrchestratorForInstance($dummyInstance, $job)->provisionManager();
                            $job->setStatus(JobStatus::INIT);
                            break;
                        case JobStatus::DELETING_ERROR:
                            OrchestratorFactory::getOrchestratorForInstance($dummyInstance, $job)->dispatchJob();
                            OrchestratorFactory::getOrchestratorForInstance($dummyInstance, $job)->removeManager();
                            $job->setStatus(JobStatus::DELETING);
                            break;
                        default:
                            throw new \RuntimeException('This is straight out impossible. Job was: ' . $job->getId(), 1567673534);
                            break;
                    }
                    App::getDbHelper()->persist($job);

                    if (!$job->getOwner()->getPreferences()->getNotifications()->isMuteAdmin()) {
                        App::getNotificationUtility()::notifyAdmin('New execution for job ' . $job->getId() . ' automatically created');
                    }
                }
            } catch (Exception $e) {
                App::getLogger()->err('Warning ' . $e->getCode() . ' during cronjob job init: ' . $e->getMessage());
                continue;
            }
        }
        App::getDbHelper()->flush();

        return 0;
    }

    protected function waitingPeriodOver(Job $job, string $gracePeriod): bool
    {
        $now = new \DateTime('now', ServerUtility::getTimezoneObject());
        $then = $job->getLatestAction();

        try {
            $gracePeriod = new \DateInterval($gracePeriod);
        } catch (Exception $e) {
            $gracePeriod = new \DateInterval($this->defaultGracePeriod);
        }

        return $then->add($gracePeriod) < $now;
    }
}
