<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\AuthenticatedController;
use Helio\Panel\Controller\Traits\ElasticController;
use Helio\Panel\Controller\Traits\ParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/user')
 *
 */
class ApiUserController extends AbstractController
{
    use AuthenticatedController;
    use ParametrizedController;
    use TypeApiController;
    use ElasticController;

    protected function getContext(): ?string
    {
        return 'panel';
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/instancelist", methods={"GET"}, name="user.serverlist")
     * @throws \Exception
     */
    public function serverListAction(): ResponseInterface
    {
        $limit = (int)($this->params['limit'] ?? 10);
        $offset = (int)($this->params['offset'] ?? 0);
        $order = explode(' ', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [$order[0] => $order[1]];

        $servers = [];
        foreach ($this->dbHelper->getRepository(Instance::class)->findByOwner($this->user, $orderBy, $limit, $offset) as $instance) {
            /** @var Instance $instance */
            $servers[] = ['id' => $instance->getId(), 'html' => $this->fetchPartial('listItemInstance', ['instance' => $instance, 'user' => $this->user])];
        }
        return $this->render(['items' => $servers, 'user' => $this->user]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/joblist", methods={"GET"}, name="user.serverlist")
     * @throws \Exception
     */
    public function jobListAction(): ResponseInterface
    {
        $limit = (int)($this->params['limit'] ?? 10);
        $offset = (int)($this->params['offset'] ?? 0);
        $order = explode(' ', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [$order[0] => $order[1]];

        $jobs = [];
        foreach ($this->dbHelper->getRepository(Job::class)->findByOwner($this->user, $orderBy, $limit, $offset) as $job) {
            /**@var Job $job */
            $jobs[] = [
                'id' => $job->getId(),
                'html' => $this->fetchPartial('listItemJob', [
                    'job' => $job,
                    'user' => $this->user,
                    'files' => array_filter(scandir(ExecUtility::getJobDataFolder($job), SCANDIR_SORT_ASCENDING), function (string $item) {
                        return strpos($item, '.') !== 0 && strpos($item, '.tar.gz') > 0;
                    }),
                ]),
                'tasks' => $this->dbHelper->getRepository(Task::class)->count(['job' => $job]),
                'open_tasks' => $this->dbHelper->getRepository(Task::class)->count(['job' => $job, 'status' => TaskStatus::READY]),
                'running_tasks' => $this->dbHelper->getRepository(Task::class)->count(['job' => $job, 'status' => TaskStatus::RUNNING]),
            ];
        }
        return $this->render(['items' => $jobs]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/update", methods={"POST"}, name="user.update")
     */
    public function updateProfileAction(): ResponseInterface
    {

        $this->optionalParameterCheck(['username' => FILTER_SANITIZE_STRING,
            'role' => FILTER_SANITIZE_STRING,
            'email' => FILTER_SANITIZE_EMAIL
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
            if (\array_key_exists($option, $this->params)) {
                $this->optionalParameterCheck([$option => FILTER_SANITIZE_STRING]);
                $config[$option] = $this->params[$option];
            }
        }
        $this->user->setConfig(json_encode($config));

        $this->dbHelper->flush($this->user);

        return $this->render(['message' => 'done']);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/logs", methods={"GET"}, name="job.logs")
     */
    public function logsAction(): ResponseInterface
    {
        return $this->render($this->elastic->getLogEntries($this->user->getId()));
    }


    /**
     * @return ResponseInterface
     * @throws \Exception
     *
     * @Route("/settoken", methods={"PUT"}, name="user.settoken")
     */
    public function generateTokenAction(): ResponseInterface
    {
        $this->user->setToken(JwtUtility::generateUserIdentificationToken($this->user));
        $this->persistUser();
        return $this->render(['message' => '<strong>Your Token is ' . $this->user->getToken() . '</strong> Safe it in your Password manager, it cannot be displayed ever again.']);
    }
}