<?php

namespace Helio\Panel\Job\Docker;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface ApiInterface - Here to collect all relevant endpoints in one documentation for the customer
 *
 */
interface ApiInterface
{

    /**
     * @OA\Post(
     *     path="/api/job/add",
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job to manipulate",
     *         required=true,
     *         @Oa\Items(
     *             type="string",
     *             enum = {"_NEW"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobtype",
     *         in="query",
     *         description="Type of the Job, it's always the same",
     *         required=true,
     *         @OA\Items(
     *             type="string",
     *             enum = {"docker"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobname",
     *         in="query",
     *         description="A name for your job, if you want to identify it later",
     *         required=false,
     *         @OA\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="cpus",
     *         in="query",
     *         description="Specify how much CPUs this job ideally gets",
     *         required=false,
     *         @OA\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="gpus",
     *         in="query",
     *         description="Specify how much GPUs this job ideally gets",
     *         required=false,
     *         @OA\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         description="Specify in which location this job should run",
     *         required=false,
     *         @OA\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="billingReference",
     *         in="query",
     *         description="A billing reference (e.g. your customer's order number)",
     *         required=false,
     *         @OA\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="budget",
     *         in="query",
     *         description="We terminate jobs automatically once they have reached the maximum budget set here",
     *         required=false,
     *         @OA\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="free",
     *         in="query",
     *         description="Let us know if you won't pay anything for your job (e.g. you're an Open Source project)",
     *         required=false,
     *         @OA\Items(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         description=">- Your Job JSON config looking like this:

            {
                ""container"": ""nginx:1.8"",
                ""env"": [
                    {""SOURCE_PATH"":""https://account-name.zone-name.web.core.windows.net""},
                    {""TARGET_PATH"":""https://bucket.s3.aws-region.amazonaws.com""}
                ],
                ""registry"": {
                    ""server"": ""example.azurecr.io"",
                    ""username"": ""$DOCKER_USER"",
                    ""password"": ""$DOCKER_PASSWORD"",
                    ""email"": ""docker@example.com""
                }
            }",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="string"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Job was created. WARNING: This request can take quite some time.",
     *         @OA\JsonContent(
     *           type="object",
     *           @OA\Property(
     *               property="token",
     *               type="string",
     *               description="The authentication token that's only valid for this job"
     *           ),
     *           @OA\Property(
     *               property="id",
     *               type="string",
     *               description="The Id of the newly created job"
     *           )
     *         )
     *     ),
     *     security={
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */


    public function addJob(): ResponseInterface;

    /**
     * @OA\Delete(
     *     path="/api/job/remove",
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job to delete",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(response="200", description="Job has been deleted"),
     *     security={
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     * @return ResponseInterface
     *
     */
    public function removeJob(): ResponseInterface;


    /**
     * @OA\Get(
     *     path="/api/job/isready",
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job which status you wandt to see",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(response="200", description="Contains the Status"),
     *     security={
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function isJobReady(): ResponseInterface;


    /**
     * @OA\Post(
     *     path="/api/exec",
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job to manipulate",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         description=">- ENV Variables formated like this

    {
    ""env"": [
    {""SOURCE_PATH"":""https://account-name.zone-name.web.core.windows.net""},
    {""TARGET_PATH"":""https://bucket.s3.aws-region.amazonaws.com""}
    ]
    }",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="string"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Create a Job",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="string",
     *                 description="boolean if the execution was successful"
     *             ),
     *             @OA\Property(
     *                 property="id",
     *                 type="string",
     *                 description="The Id of the newly created execution"
     *             )
     *         )
     *     ),
     *     security={
     *         {"authByJobtoken": {"any"}},
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface;


    /**
     * @OA\Post(
     *     path="/api/exec/work/submitresult",
     *     @OA\Parameter(
     *         name="executionid",
     *         in="query",
     *         description="Id of the current Execution",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job that the execution belongs to",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         description=">- Arbitrary Job result data as JSON

    {
    ""success"":true
    }",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="string"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Create a Job",
     *         @OA\JsonContent(
     *           type="object",
     *           @OA\Property(
     *               property="success",
     *               type="string",
     *               description="boolean if the execution was successful"
     *           )
     *         )
     *     ),
     *     security={
     *         {"authByJobtoken": {"any"}},
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function submitresult(): ResponseInterface;


    /**
     * @OA\Get(
     *     path="/api/job/logs",
     *     description="Aggregation of the logs of all executions of a job",
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job which logs you wandt to see",
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
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function jobLogs(): ResponseInterface;


    /**
     * @OA\Get(
     *     path="/api/exec/logs",
     *     description="Logs of a execution",
     *     @OA\Parameter(
     *         name="executionid",
     *         in="query",
     *         description="Id of the execution which logs you wandt to see",
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
    public function executionLogs(): ResponseInterface;


    /**
     * @OA\Get(
     *     path="/api/user/logs",
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
     */
    public function userLogs(): ResponseInterface;

    /**
     * @OA\Get(
     *     path="/api/user/strangelogs",
     *     description="Aggregation of logs not associated to a execution or job",
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
    public function strangeLogs(): ResponseInterface;
}