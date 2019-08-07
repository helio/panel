<?php

namespace Helio\Panel\Job\Busybox;

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
     * TODO: Implement if necessary
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
            ->setImage('gitlab.idling.host:4567/helio/runner/busybox:latest')
            ->setArgs(['/bin/sh', '-c', '\'i=0; while [ "$i" -le "${LIMIT:-5}" ]; do echo "$i: $(date)"; i=$((i+1)); sleep 10; done\''])
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
                'LIMIT' => $this->execution ? $this->execution->getConfig('limit', 100) : 100
            ]);
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     *
     * TODO: Implement if necessary
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool
    {
        return true;
    }
}