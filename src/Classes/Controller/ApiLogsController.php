<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\Controller\Traits\ModelUserController;
use Helio\Panel\Controller\Traits\HelperElasticController;
use Helio\Panel\Controller\Traits\ModelParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/logs')
 */
class ApiLogsController extends AbstractController
{
    use ModelUserController;
    use ModelParametrizedController;
    use TypeApiController;
    use HelperElasticController;

    /**
     * @OA\Get(
     *     path="/logs",
     *     tags={"logs"},
     *     description="Aggregation of logs not associated to an execution or job",
     *     security={
     *         {"authByApitoken": {"any"}}
     *     },
     *     @OA\Response(response="200", ref="#/components/responses/logs"),
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
     *
     * @throws Exception
     *
     * @Route("", methods={"GET"}, name="logs")
     */
    public function logsAction(): ResponseInterface
    {
        $this->optionalParameterCheck([
            'size' => FILTER_SANITIZE_NUMBER_INT,
            'from' => FILTER_SANITIZE_NUMBER_INT,
        ]);

        $size = array_key_exists('size', $this->params) ? $this->params['size'] : 10;
        $from = array_key_exists('from', $this->params) ? $this->params['from'] : 0;

        return $this->render($this->setWindow($from, $size)->getWeirdLogEntries($this->user->getId()));
    }
}
