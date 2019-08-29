<?php

namespace Helio\Panel\Command;

use Ahc\Cron\Expression;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\App;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Preferences\NotificationPreferences;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Utility\MiddlewareForCliUtility;
use Helio\Panel\Utility\NotificationUtility;
use Slim\Http\Environment;
use Slim\Http\Request;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony console command.
 *
 * Class ExecuteScheduledJob
 */
class ExecuteScheduledJob extends Command
{
    /** @var array */
    protected $middlewaresToApply;

    /** @var App */
    protected $app;

    /**
     * ExecuteScheduledJob constructor.
     * @param  string    $appClassName
     * @param  array     $middlewaresToApply
     * @throws Exception
     */
    public function __construct(string $appClassName = App::class, $middlewaresToApply = [MiddlewareForCliUtility::class])
    {
        parent::__construct();
        $this->middlewaresToApply = $middlewaresToApply;
        $request = Request::createFromEnvironment(Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
        ]));

        /* @var App $appClassName */
        $this->app = $appClassName::getApp('cli', $this->middlewaresToApply);
        $this->app->getContainer()['request'] = $request;
    }

    /**
     * Configure Command.
     */
    protected function configure(): void
    {
        $this->setName('app:execute-scheduled-jobs')
            ->setDescription('Checks all Jobs for due scheduled executions and runs them if necessary.')
            ->setHelp('This task should run as often as possible through a cron.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     *
     * @throws Exception
     * @throws GuzzleException
     */
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
                    $execution = (new Execution())->setStatus(ExecutionStatus::READY)->setJob($job)->setCreated()->setName('created by CLI');
                    $pseudoInstance = new Instance();

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

                    if (!$job->getOwner()->getNotificationPreference(NotificationPreferences::MUTE_ADMIN)) {
                        NotificationUtility::notifyAdmin('New execution for job ' . $job->getId() . ' automatically created');
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
