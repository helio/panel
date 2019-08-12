<?php

namespace Helio\Panel\Job\Gitlab;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Execute extends AbstractExecute
{

    /**
     * @return DispatchConfig
     * @throws Exception
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setImage('gitlab/gitlab-runner')
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job),
                'GITLAB_TAGS' => $this->job->getConfig('gitlabTags')
            ]);
    }


    /**
     * @param array $config
     * @return bool
     * @throws Exception
     */
    public function create(array $config): bool
    {
        $options = [
            'gitlabEndpoint' => FILTER_SANITIZE_URL,
            'gitlabToken' => FILTER_SANITIZE_STRING,
            'gitlabTags' => FILTER_SANITIZE_STRING
        ];

        $cleanConfig = [];
        foreach ($options as $name => $filter) {
            if (array_key_exists($name, $config)) {
                $cleanConfig[$name] = filter_var($config[$name], $filter);
            }
        }
        $this->job->setConfig($cleanConfig);
        $this->execution->setEstimatedRuntime(0);

        App::getDbHelper()->persist($this->execution);
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