<?php

namespace Helio\Panel\Job\Blender;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Repositories\ExecutionRepository;
use Helio\Panel\Repositories\JobRepository;
use Helio\Panel\Service\ExecutionService;
use Helio\Panel\Utility\ServerUtility;

class Execute extends \Helio\Panel\Job\Docker\Execute
{
    /**
     * @var string
     */
    private $dockerImage;
    /**
     * @var array
     */
    private $dockerRegistry;
    /**
     * @var string
     */
    private $storageBucketName;
    /**
     * @var string
     */
    private $storageCredentials;

    public function __construct(Job $job, Execution $execution = null)
    {
        parent::__construct($job, $execution);

        $this->dockerImage = ServerUtility::get('BLENDER_DOCKER_IMAGE');
        $this->dockerRegistry = [
            'server' => ServerUtility::get('BLENDER_DOCKER_REGISTRY_SERVER'),
            'username' => ServerUtility::get('BLENDER_DOCKER_REGISTRY_USERNAME'),
            'password' => ServerUtility::get('BLENDER_DOCKER_REGISTRY_PASSWORD'),
            'email' => ServerUtility::get('BLENDER_DOCKER_REGISTRY_EMAIL'),
        ];
        $this->storageBucketName = ServerUtility::get('BLENDER_STORAGE_BUCKET_NAME');
        $this->storageCredentials = str_replace("\n", '', file_get_contents(ServerUtility::get('BLENDER_STORAGE_CREDENTIALS_JSON_PATH')));
    }

    public function create(array $jobObject): bool
    {
        $jobObject['labels'] = ['render'];

        return parent::create($jobObject);
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
        parent::run($config);

        $replicas = 1;
        /** @var JobRepository $jobRepository */
        $jobRepository = App::getDbHelper()->getRepository(Job::class);

        $type = $this->job->getConfig('type');

        // sliding window only for render jobs, estimation jobs should have replica = 1 always (=> higher prio for estimates)
        if ('render' === $type) {
            // sliding window:
            // we set the replica count for only one execution to 1. Whenever a new worker gets created
            // another execution gets a replica of 1. When an execution finishes, the next execution gets replica of 1.
            // As soon as all executions of this job are done, find executions which still need to run in other jobs and update replica count there.
            $runningExecution = $jobRepository->getExecutionCountHavingReplicas($this->job->getLabels());

            if ($runningExecution >= 1) {
                $replicas = 0;
            }
        }

        $this->execution->setReplicas($replicas);

        App::getApp()->getDbHelper()->persist($this->execution);
        App::getApp()->getDbHelper()->flush();

        return true;
    }

    public function executionDone(string $stats): bool
    {
        if (!parent::executionDone($stats)) {
            return false;
        }
        $dbHelper = App::getDbHelper();

        $this->execution->setReplicas(0);
        $dbHelper->persist($this->execution);

        /** @var ExecutionRepository $executionRepository */
        $executionRepository = $dbHelper->getRepository(Execution::class);
        $executionsService = new ExecutionService($executionRepository);

        if ($executionsService->setNextExecutionActive($this->job)) {
            return true;
        }

        // ensure flush in case it reaches here.
        $dbHelper->flush();

        return true;
    }

    /**
     * @return DispatchConfig
     *
     * @throws Exception
     */
    public function getDispatchConfig(): DispatchConfig
    {
        $cfg = parent::getDispatchConfig();

        $cfg->setImage($this->dockerImage);
        $cfg->setRegistry($this->dockerRegistry);

        return $cfg;
    }

    protected function getCommonEnvVariables(): array
    {
        $env = parent::getCommonEnvVariables();

        $env['STORAGE_BUCKET_NAME'] = $this->storageBucketName;
        $env['STORAGE_CREDENTIALS'] = $this->storageCredentials;
        // engine is hardcoded for now, as we don't support any other engine.
        $env['BLENDER_ENGINE'] = 'CYCLES';
        $env['RENDER_SCRIPT_PATH'] = '/docker-blender/render.py';

        // pass user id for access control on GCS bucket objects
        $env['HELIO_USER_ID'] = $this->job->getOwner()->getId();

        return $env;
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int
    {
        return $this->execution->getConfig('estimated_runtime', 3600);
    }
}
