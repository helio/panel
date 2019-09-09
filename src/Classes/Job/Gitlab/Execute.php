<?php

namespace Helio\Panel\Job\Gitlab;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;

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
            ->setImage('hub.helio.dev:4567/helio/runner/gitlab:latest')
            ->setEnvVariables(array_merge($this->getCommonEnvVariables(), [
                'GITLAB_TAGS' => $this->job->getConfig('gitlabTags'),
                'GITLAB_TOKEN' => $this->job->getConfig('gitlabToken'),
                'GITLAB_URL' => $this->job->getConfig('gitlabEndpoint'),
                'GITLAB_RUNNER_NAME' => 'helio-runner-' . $this->job->getId(),
            ]));
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
            'gitlabToken' => FILTER_SANITIZE_STRING | FILTER_SANITIZE_MAGIC_QUOTES,
            'gitlabTags' => FILTER_SANITIZE_STRING | FILTER_SANITIZE_MAGIC_QUOTES,
        ];

        $cleanConfig = [];
        foreach ($options as $name => $filter) {
            if (array_key_exists('config', $jobObject) && array_key_exists($name, $jobObject['config'])) {
                $cleanConfig[$name] = filter_var($jobObject['config'][$name], $filter);
            }
        }
        $this->job->setConfig($cleanConfig);

        App::getDbHelper()->persist($this->job);
        App::getDbHelper()->flush($this->job);

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
