<?php

namespace Helio\Panel;

/**
 * Interface ApiInterface for swagger documentation
 *
 * @package Helio\Panel
 *
 * @OA\Info(title="Helio API", version="0.0.1")
 *
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="authByApitoken",
 *     name="Authorization",
 *     description="The API Token of your user, obtainable in the WebUI at panel.idling.host"
 * )
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="authByJobtoken",
 *     name="Authorization",
 *     description="The Job specific token received during /api/job/add"
 * )
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="authByInstancetoken",
 *     name="Authorization",
 *     description="The Instance specific token received during registering an instance"
 * )
 *
 * @OA\Server(url="https://panel.idling.host")
 * @OA\Server(url="https://panelprev.idling.host")
 * @OA\Server(url="http://localhost:8099")
 *
 */
interface ApiInterface
{
}