<?php
namespace Helio\Panel\Job\Vfdocker;

use Psr\Http\Message\ResponseInterface;

class Api implements ApiInterface {

    /**
     *     path="/api/job/add",
     */
    public function addJob(): ResponseInterface
    {
        //return ApiJobController::
    }

    /**
     *     path="/api/job/remove",
     *
     */
    public function removeJob(): ResponseInterface
    {
        // TODO: Implement removeJob() method.
    }

    /**
     *     path="/api/job/isready",
     */
    public function isJobReady(): ResponseInterface
    {
        // TODO: Implement isJobReady() method.
    }

    /**
     *     path="/exec",
     */
    public function execute(): ResponseInterface
    {
        // TODO: Implement execute() method.
    }

    /**
     *     path="/exec/work/submitresult",
     */
    public function submitresult(): ResponseInterface
    {
        // TODO: Implement submitresult() method.
    }

    /**
     *     path="/api/job/logs"
     */
    public function jobLogs(): ResponseInterface
    {
        // TODO: Implement jobLogs() method.
    }

    /**
     * @OA\Get(
     *     path="/exec/logs",
     *     description="Logs of a task",
     *     @OA\Parameter(
     *         name="taskid",
     *         in="query",
     *         description="Id of the task which logs you wandt to see",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the associated job, needed for authentication and authorisation",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
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
     *     ),
     *     @OA\Response(response="200", description="Contains the Status"),
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function taskLogs(): ResponseInterface
    {
        // TODO: Implement taskLogs() method.
    }

    /**
     * @OA\Get(
     *     path="/api/user/logs",
     *     description="Aggregation of the logs of all jobs and tasks of a user",
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
     */
    public function userLogs(): ResponseInterface
    {
        // TODO: Implement userLogs() method.
    }

    /**
     * @OA\Get(
     *     path="/api/user/strangelogs",
     *     description="Aggregation of logs not associated to a task or job",
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
     */
    public function strangeLogs(): ResponseInterface
    {
        // TODO: Implement strangeLogs() method.
    }
}