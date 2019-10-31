<?php

namespace Helio\Panel\Job\Blender;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Orchestrator\OrchestratorFactory;
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
        $count = App::getDbHelper()->getRepository(Execution::class)->count([
            'job' => $this->job,
            'replicas' => 1,
        ]);
        if ($count >= ServerUtility::get('BLENDER_PARALLEL_REPLICA_COUNT', 5)) {
            $replicas = 0;
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

        $this->execution->setReplicas(0);
        $executionRepository = App::getDbHelper()->getRepository(Execution::class);
        $executions = $executionRepository->findBy(['job' => $this->job, 'status' => ExecutionStatus::READY, 'replicas' => 0], ['priority' => 'ASC', 'created' => 'ASC'], 5);
        /** @var Execution $execution */
        foreach ($executions as $execution) {
            try {
                /** @var Execution $lockedExecution */
                $lockedExecution = $executionRepository->find($execution->getId(), LockMode::OPTIMISTIC, $execution->getVersion());
                $lockedExecution->setReplicas(1);
                App::getDbHelper()->persist($this->execution);
                App::getDbHelper()->persist($execution);
                App::getDbHelper()->flush();

                // scale services accordingly
                return OrchestratorFactory::getOrchestratorForInstance(new Instance(), $this->job)->dispatchReplicas([$this->execution, $lockedExecution]);
            } catch (OptimisticLockException $e) {
                // trying next execution if the current one was modified in the meantime
            }
        }

        if (!empty($executions)) {
            LogHelper::warn('Executions that need scale-up found but not scaled up. Lock problem?', $executions);
        }

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
