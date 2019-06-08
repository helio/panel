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
 * @OA\SecurityScheme(
 *     type="apiKey",
 *     in="query",
 *     securityScheme="authByInstancetoken",
 *     name="token"
 * )
 *
 */
interface ApiInterface
{
}