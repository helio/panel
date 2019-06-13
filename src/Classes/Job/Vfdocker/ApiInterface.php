<?php

namespace Helio\Panel\Job\Vfdocker;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface ApiInterface - Here to collect all relevant endpoints in one documentation for the customer
 *
 * @package Helio\Panel\Job\VfDocker
 *
 *
 * @OA\Info(title="Docker Dispatch Api", version="0.0.1")
 *
 * @OA\SecurityScheme(
 *     type="apiKey",
 *     in="query",
 *     securityScheme="authByApitoken",
 *     name="token"
 * )
 * @OA\SecurityScheme(
 *     type="apiKey",
 *     in="query",
 *     securityScheme="authByJobtoken",
 *     name="token"
 * )
 *
 * @OA\Server(url="https://panel.idling.host")
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
     *             enum = {"vfdocker"}
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
                {""SOURCE_PATH"":""https://account-name>.<zone-name>.web.core.windows.net""},
                {""TARGET_PATH"":""https://bucket.s3.aws-region.amazonaws.com""}
            ],
            ""registry"": {
                ""server"": ""vattenfall.azurecr.io"",
                ""username"": ""$DOCKER_USER"",
                ""password"": ""$DOCKER_PASSWORD"",
                ""email"": ""docker@vattenfall.se""
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
     *         {"authByJobtoken": {"any"}},
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
     *         {"authByJobtoken": {"any"}},
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function isJobReady(): ResponseInterface;


    /**
     * @OA\Post(
     *     path="/exec",
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
                {""SOURCE_PATH"":""https://account-name>.<zone-name>.web.core.windows.net""},
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
     *           type="object",
     *           @OA\Property(
     *               property="success",
     *               type="string",
     *               description="boolean if the execution was successful"
     *           )
     *         )
     *     ),
     *     security={
     *         {"authByJobtoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface;


    /**
     * @OA\Post(
     *     path="/exec/work/submitresult",
     *     @OA\Parameter(
     *         name="taskid",
     *         in="query",
     *         description="Id of the current Task",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job that the task belongs to",
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
     *         {"authByJobtoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function submitresult(): ResponseInterface;
}