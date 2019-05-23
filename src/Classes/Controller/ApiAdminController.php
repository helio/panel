<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\AdminController;
use Helio\Panel\Controller\Traits\InstanceController;
use Helio\Panel\Controller\Traits\JobController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @OA\Info(title="Admin API", version="0.0.1")
 *
 * @RoutePrefix('/api/admin')
 *
 */
class ApiAdminController extends AbstractController
{
    use InstanceController, JobController {
        InstanceController::setupParams insteadof JobController;
        InstanceController::requiredParameterCheck insteadof JobController;
        InstanceController::optionalParameterCheck insteadof JobController;
    }
    use AdminController;
    use TypeDynamicController;

    public function __construct()
    {
        parent::__construct();
        $this->setMode('api');
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
        $order = explode(',', filter_var($this->params['orderby'] ?? 'status DESC, priority ASC', FILTER_SANITIZE_STRING));
        $orderBy = [];
        foreach ($order as $field) {
            $field = explode(' ', trim($field));
            $orderBy[$field[0]] = $field[1];
        }

        $servers = [];
        foreach ($this->dbHelper->getRepository(Instance::class)->findBy([], $orderBy, $limit, $offset) as $instance) {
            /** @var Instance $instance */
            $servers[] = ['id' => $instance->getId(), 'html' => $this->fetchPartial('listItemInstance', ['instance' => $instance, 'user' => $this->user, 'admin' => true])];
        }
        return $this->render(['items' => $servers, 'user' => $this->user]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/joblist", methods={"GET"}, name="admin.joblist")
     * @throws \Exception
     */
    public function jobListAction(): ResponseInterface
    {
        $limit = (int)($this->params['limit'] ?? 10);
        $offset = (int)($this->params['offset'] ?? 0);
        $order = explode(',', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [];
        foreach ($order as $field) {
            $field = explode(' ', trim($field));
            $orderBy[$field[0]] = $field[1];
        }

        $jobs = [];
        foreach ($this->dbHelper->getRepository(Job::class)->findBy([], $orderBy, $limit, $offset) as $job) {
            /**@var Job $job */
            $jobs[] = ['id' => $job->getId(), 'html' => $this->fetchPartial('listItemJob', ['job' => $job, 'user' => $this->user, 'admin' => true])];
        }
        return $this->render(['items' => $jobs, 'user' => $this->user]);
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
        $this->dbHelper->flush($this->user);

        return $this->render();
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/stat", methods={"GET"}, name="exec.stats")
     * @throws \Exception
     */
    public function statsAction(): ResponseInterface
    {
        $now = new \DateTime('now', ServerUtility::getTimezoneObject());
        $stale = $now->sub(new \DateInterval('PT1H'));

        $avgWaitQuery = $this->dbHelper->getRepository(Task::class)->createQueryBuilder('avg_wait');
        $avgWaitQuery->select('AVG(TIMESTAMPDIFF(SECOND, avg_wait.created, ' . $now->format('YmdHis') . ')) as avg')->where('avg_wait.status = ' . TaskStatus::READY);
        $staleQuery = $this->dbHelper->getRepository(Task::class)->createQueryBuilder('stale');
        $staleQuery->select('COUNT(stale.id) as count')->where('stale.status = ' . TaskStatus::RUNNING)->andWhere('TIMESTAMPDIFF(SECOND, stale.latestAction, ' . $stale->format('YmdHis') . ') > 600');

        return $this->render([
            'active_jobs' => $this->dbHelper->getRepository(Job::class)->count(['status' => JobStatus::READY]),
            'running_tasks' => $this->dbHelper->getRepository(Task::class)->count(['status' => TaskStatus::RUNNING]),
            'waiting_tasks' => $this->dbHelper->getRepository(Task::class)->count(['status' => TaskStatus::READY]),
            'done_tasks' => $this->dbHelper->getRepository(Task::class)->count(['status' => TaskStatus::DONE]),
            'task_avg_wait' => $avgWaitQuery->getQuery()->getArrayResult()[0]['avg'],
            'stale_tasks' => $staleQuery->getQuery()->getArrayResult()[0]['count']
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/getRunnersHiera", methods={"GET"}, name="admin.getJobHiera")
     */
    public function openJobsListForPuppetAction(): ResponseInterface
    {
        $jobs = $this->dbHelper->getRepository(Job::class)->findBy(['status' => JobStatus::READY]);

        $jobList = [];
        $counter = 0;
        /** @var Job $job */
        foreach ($jobs as $job) {
            $jobList[] = [
                'job_number' => ++$counter,
                'job_specs' => [
                    'job_id' => $job->getId(),
                    'service_name' => $job->getType() . '-' . $job->getId()
                ]
            ];
        }


        return $this->render(['name' => 'profile::docker::backlog', 'jobs' => $jobList]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/getJobHiera", methods={"GET"}, name="admin.getJobHiera")
     */
    public function jobConfigForPuppetAction(): ResponseInterface
    {
        $dcf = JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig();
        $servicename = $this->job->getType() . '-' . $this->job->getId();

        $env = [];
        if ($dcf->getEnvVariables()) {
            foreach ($dcf->getEnvVariables() as $key => $value) {
                $env[] = "$key=$value";
            }
        }

        return $this
            ->setReturnType('yaml')
            ->render([
                    'profile::docker::clusters' => [
                        $servicename => [
                            'service_name' => $servicename,
                            'image' => $dcf->getImage(),
                            'replicas' => $dcf->getReplicaCountForJob($this->job),
                            'env' => $env
                        ]
                    ]
                ]
            );
    }
}
