<?php

namespace Helio\Panel\Controller;

use \Exception;
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
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/logs')
 *
 */
class ApiLogsController extends AbstractController
{
    use ModelUserController;
    use ModelParametrizedController;
    use TypeApiController;
    use HelperElasticController;

    protected function getContext(): ?string
    {
        return 'panel';
    }

    /**
     * @OA\Get(
     *     path="/logs",
     *     description="Aggregation of the logs of all jobs and executions of a user",
     *     @OA\Response(response="200", description="Contains the Status"),
     *     security={
     *         {"authByApitoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="Amount of log entries to retreive",
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Amount of log entries to skip",
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("", methods={"GET"}, name="logs")
     */
    public function logsAction(): ResponseInterface
    {
        return $this->render($this->setWindow()->getLogEntries($this->user->getId()));
    }


    /**
     * @OA\Get(
     *     path="/logs/strange",
     *     description="Aggregation of logs not associated to a execution or job",
     *     @OA\Response(response="200", description="Contains the Logs"),
     *     security={
     *         {"authByApitoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="Amount of log entries to retreive",
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Amount of log entries to skip",
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/strange", methods={"GET"}, name="logs.strange")
     */
    public function strangeLogsAction(): ResponseInterface
    {
        return $this->render($this->setWindow()->getWeirdLogEntries($this->user->getId()));
    }
}