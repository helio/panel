<?php

namespace Helio\Panel\Job\Gitlab;

use \Exception;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Execute extends AbstractExecute
{

    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface $response
     *
     * @return bool
     *
     * TODO: Implement
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response): bool
    {
        return true;
    }


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
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool
    {
        $options = [
            'gitlabEndpoint' => FILTER_SANITIZE_URL,
            'gitlabToken' => FILTER_SANITIZE_STRING,
            'gitlabTags' => FILTER_SANITIZE_STRING
        ];

        $config = [];
        foreach ($options as $name => $filter) {
            $key = filter_var($name, FILTER_SANITIZE_STRING);
            if (array_key_exists($key, $params)) {
                $config[$key] = filter_var($params[$key], $filter);
            }
        }
        $this->job->setConfig($config);
        return true;
    }
}