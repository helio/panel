<?php

namespace Helio\Panel\Job\Gitlab;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\JwtUtility;

class Execute extends AbstractExecute
{
    /**
     * @return DispatchConfig
     *
     * @throws Exception
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setImage('gitlab/gitlab-runner')
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job),
                'GITLAB_TAGS' => $this->job->getConfig('gitlabTags'),
            ]);
    }

    /**
     * @param array $jobObject
     *
     * @return bool
     *
     * @throws Exception
     */
    public function create(array $jobObject): bool
    {
        parent::create($jobObject);
        $options = [
            'gitlabEndpoint' => FILTER_SANITIZE_URL,
            'gitlabToken' => FILTER_SANITIZE_STRING,
            'gitlabTags' => FILTER_SANITIZE_STRING,
        ];

        $cleanConfig = [];
        foreach ($options as $name => $filter) {
            if (array_key_exists('config', $jobObject) && array_key_exists($name, $jobObject['config'])) {
                $cleanConfig[$name] = filter_var($jobObject['config'][$name], $filter);
            }
        }
        $this->job->setConfig($cleanConfig);

        App::getDbHelper()->persist($this->job);
        App::getDbHelper()->flush();

        return true;
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int
    {
        return 0;
    }
}
