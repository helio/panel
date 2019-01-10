<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\AuthenticatedController;
use Helio\Panel\Controller\Traits\ParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
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
            $jobs[] = ['id' => $job->getId(), 'html' => $this->fetchPartial('listItemJob', [
                'job' => $job,
                'user' => $this->user,
                'files' => array_filter(scandir(ExecController::getJobDataFolder($job), SCANDIR_SORT_ASCENDING), function (string $item) {
                    return strpos($item, '.') !== 0;
                })
            ])];
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
}