<?php

namespace Helio\Panel\Job\Vfdocker;

use Helio\Panel\Job\JobInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface ApiInterface - Here to collect all relevant endpoints in one documentation for the customer
 *
 * @package Helio\Panel\Job\VfDocker
 *
 * @OA\Info(title="Docker Dispatch Api for  customerMarkets", version="0.0.1")
 */
interface ApiInterface extends JobInterface
{

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
}