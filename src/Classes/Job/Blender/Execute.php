<?php

namespace Helio\Panel\Job\Blender;

use Exception;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
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
        $this->storageCredentials = file_get_contents(ServerUtility::get('BLENDER_STORAGE_CREDENTIALS_JSON_PATH'));
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
