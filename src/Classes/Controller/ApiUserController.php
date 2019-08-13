<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\ModelUserController;
use Helio\Panel\Controller\Traits\HelperElasticController;
use Helio\Panel\Controller\Traits\ModelParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/user')
 */
class ApiUserController extends AbstractController
{
    use ModelUserController;
    use ModelParametrizedController;
    use TypeApiController;
    use HelperElasticController;

    /**
     * @return ResponseInterface
     *
     * @Route("/instancelist", methods={"GET"}, name="user.serverlist")
     *
     * @throws Exception
     */
    public function serverListAction(): ResponseInterface
    {
        $limit = (int) ($this->params['limit'] ?? 10);
        $offset = (int) ($this->params['offset'] ?? 0);
        $order = explode(' ', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [$order[0] => $order[1]];

        $servers = [];
        foreach (App::getDbHelper()->getRepository(Instance::class)->findBy(['owner' => $this->user], $orderBy, $limit, $offset) as $instance) {
            /* @var Instance $instance */
            $servers[] = ['id' => $instance->getId(), 'html' => $this->fetchPartial('listItemInstance', ['instance' => $instance, 'user' => $this->user])];
        }

        return $this->render(['items' => $servers, 'user' => $this->user]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/joblist", methods={"GET"}, name="user.serverlist")
     *
     * @throws Exception
     */
    public function jobListAction(): ResponseInterface
    {
        $limit = (int) ($this->params['limit'] ?? 10);
        $offset = (int) ($this->params['offset'] ?? 0);
        $includeTerminated = (bool) ($this->params['deleted'] ?? 0);
        $order = explode(' ', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [$order[0] => $order[1]];
        $searchCriteria = ['owner' => $this->user];

        // if the user wants to see terminated workloads, skip the status filter
        if (!$includeTerminated) {
            $searchCriteria['status'] = JobStatus::getAllButDeletedStatusCodes();
        }

        $jobs = [];
        foreach (App::getDbHelper()->getRepository(Job::class)->findBy($searchCriteria, $orderBy, $limit, $offset) as $job) {
            /** @var Job $job */
            $jobs[] = [
                'id' => $job->getId(),
                'html' => $this->fetchPartial('listItemJob', [
                    'job' => $job,
                    'user' => $this->user,
                    'files' => array_filter(scandir(ExecUtility::getJobDataFolder($job), SCANDIR_SORT_ASCENDING), function (string $item) {
                        return 0 !== strpos($item, '.') && strpos($item, '.tar.gz') > 0;
                    }),
                ]),
                'executions' => App::getDbHelper()->getRepository(Execution::class)->count(['job' => $job]),
                'open_executions' => App::getDbHelper()->getRepository(Execution::class)->count(['job' => $job, 'status' => ExecutionStatus::READY]),
                'running_executions' => App::getDbHelper()->getRepository(Execution::class)->count(['job' => $job, 'status' => ExecutionStatus::RUNNING]),
            ];
        }

        return $this->render(['items' => $jobs]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/update", methods={"POST"}, name="user.update")
     */
    public function updateProfileAction(): ResponseInterface
    {
        $this->optionalParameterCheck(['username' => FILTER_SANITIZE_STRING,
            'role' => FILTER_SANITIZE_STRING,
            'email' => FILTER_SANITIZE_EMAIL,
        ]);

        if (array_key_exists('username', $this->params)) {
            $this->user->setName($this->params['username']);
        }
        if (array_key_exists('role', $this->params)) {
            $this->user->setRole($this->params['role']);
        }
        if (array_key_exists('email', $this->params)) {
            $this->user->setEmail($this->params['email']);
        }

        $config = json_decode($this->user->getConfig(), true);
        $validConfigOptions = ['gitlabtags', 'instancelevel', 'instancelocation'];
        foreach ($validConfigOptions as $option) {
            if (array_key_exists($option, $this->params)) {
                $this->optionalParameterCheck([$option => FILTER_SANITIZE_STRING]);
                $config[$option] = $this->params[$option];
            }
        }
        $this->user->setConfig(json_encode($config));

        App::getDbHelper()->flush($this->user);

        return $this->render(['message' => 'done']);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/settoken", methods={"PUT"}, name="user.settoken")
     */
    public function generateTokenAction(): ResponseInterface
    {
        $this->optionalParameterCheck(['eternal', FILTER_SANITIZE_STRING]);
        $duration = (array_key_exists('eternal', $this->params) && (bool) $this->params['eternal']) ? 'sticky' : null;
        $token = JwtUtility::generateToken($duration, $this->user)['token'];

        return $this->render(['message' => "<strong>Your Token is $token</strong> Safe it in your Password manager, it cannot be displayed ever again."]);
    }
}
