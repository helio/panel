<?php

namespace Helio\Panel\Command;

use DateTime;
use Doctrine\DBAL\Types\Type;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Execution\ExecutionStatus;
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

class MaintenanceRerunHangingExecution extends AbstractCommand
{
    /** @var string $gracePeriod DateInterval specifying how long we should wait before retry */
    protected $defaultGracePeriod = 'PT5H';

    protected function configure(): void
    {
        $this->addArgument('gracePeriod', InputArgument::OPTIONAL, 'DateInterval: How long the command should wait befor rerunning', $this->defaultGracePeriod);
        $this->setName('app:maintenance-rerun-hanging-executions')
            ->setDescription('Runs a hanging execution again.')
            ->setHelp('This task can be safely run every minute. It locks the execution internally.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $dummyInstance = (new Instance())->setName('___NEW')->setStatus(InstanceStatus::UNKNOWN);
        $now = new DateTime('now', ServerUtility::getTimezoneObject());
        try {
            $then = $now->sub(new \DateInterval($input->getArgument('gracePeriod') ?: $this->defaultGracePeriod));
        } catch (Exception $e) {
            $then = $now->sub(new \DateInterval($this->defaultGracePeriod));
        }

        $query = App::getDbHelper()->getRepository(Execution::class)->createQueryBuilder('e');
        $query
            ->join(Job::class, 'j')
            ->where(
                $query->expr()->andX()
                    ->add($query->expr()->in('j.status', JobStatus::getRunningStatusCodes()))
                    ->add($query->expr()->orX()
                        ->add($query->expr()->andX()
                            ->add($query->expr()->eq('e.status', ExecutionStatus::RUNNING))
                            ->add($query->expr()->isNotNull('e.latestHeartbeat'))
                            ->add($query->expr()->lt('e.latestHeartbeat', ':then')))
                        ->add($query->expr()->andX()
                            ->add($query->expr()->eq('e.status', ExecutionStatus::READY))
                            ->add($query->expr()->lt('e.created', ':then'))
                        )
                    )
            )
            ->setParameter('then', $then, Type::DATETIME);

        $executions = $query->getQuery()->getResult();
        if (empty($executions)) {
            return 0;
        }

        /** @var Execution $execution */
        foreach ($executions as $execution) {
            try {
                App::getLogger()->debug('Rerunning hanging execution with ID ' . $execution->getId());

                $execution->resetStarted()->resetLatestHeartbeat()->setStatus(ExecutionStatus::READY);
                OrchestratorFactory::getOrchestratorForInstance($dummyInstance, $execution->getJob())->dispatchJob();
                App::getDbHelper()->persist($execution);
                App::getDbHelper()->persist($execution->getJob());

                if (!$execution->getJob()->getOwner()->getPreferences()->getNotifications()->isMuteAdmin()) {
                    NotificationUtility::notifyAdmin('Execution ' . $execution->getId() . ' was resetted');
                }
            } catch (Exception $e) {
                App::getLogger()->err('Warning ' . $e->getCode() . ' during cronjob job init: ' . $e->getMessage());
                continue;
            }
        }
        App::getDbHelper()->flush();

        return 0;
    }
}
