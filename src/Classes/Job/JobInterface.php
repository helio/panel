<?php
namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface JobInterface
 *
 * @package Helio\Panel\Job
 *
 * @OA\SecurityScheme(
 *     type="apiKey",
 *     in="query",
 *     securityScheme="authByApitoken",
 *     name="token"
 * )
 */
interface JobInterface {


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
     *         description=">- Your Job YAML config looking like this:

    containers:
    - name: nginx
    image: nginx:1.8
    ports:
    - containerPort: 80
    env:
    - name: SOURCE_PATH
    value: 'https://account-name>.<zone-name>.web.core.windows.net'
    - name: TARGET_PATH
    value: 'https://bucket.s3.aws-region.amazonaws.com'
    registry:
    - name: myregistrykey
    type: docker-registry
    literals:
    - docker-server=${some-registry-name}.azurecr.io
    - docker-username=DOCKER_USER
    - docker-password=DOCKER_PASSWORD
    - docker-email=${some-email-address}",
     *         @OA\MediaType(
     *             mediaType="application/x-yaml",
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
     *     @OA\Response(response="200", description="Contains the Status")
     * ),
     *     security={
     *         {"authByApitoken": {"any"}}
     *     }
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
     *             type="string",
     *             enum = {"_NEW"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         description="The token of the *JOB* (received in the response of /api/job/add)",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         description=">- ENV Variables formated like this

    env:
    - name: SOURCE_PATH
    value: 'https://account-name>.<zone-name>.web.core.windows.net'
    - name: TARGET_PATH
    value: 'https://bucket.s3.aws-region.amazonaws.com'",
     *         @OA\MediaType(
     *             mediaType="application/x-yaml",
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
     *         {"authByApitoken": {"any"}}
     *     }
     * )
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface;
    public function __construct(Job $job);

    public function run(array $params, RequestInterface $request, ResponseInterface $response);

    public function stop(array $params, RequestInterface $request);

    public function create(array $params, RequestInterface $request): bool;
}