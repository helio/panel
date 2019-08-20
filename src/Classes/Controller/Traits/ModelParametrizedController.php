<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use RuntimeException;
use Slim\Http\Request;

/**
 * Trait ModelParametrizedController.
 *
 * @property array params
 * @property Request $request
 */
trait ModelParametrizedController
{
    /**
     * @var array
     */
    protected $params;

    /**
     * @param RouteInfo $route
     *
     * @return bool
     */
    public function setupParams(RouteInfo $route): bool
    {
        if (!$this->params) {
            // FIXME(michael): this should return in a 400 bad request but doesn't currently.
            $body = \GuzzleHttp\json_decode($this->request->getBody(), true);
            $this->params = array_merge($body, $this->request->getParams() ?? [], $route->params ?? []);
        }

        return true;
    }

    /**
     * @param array $params array formated like <parameter name> => <type>
     *
     * NOTE: Only call this with SANITIZE Filters, VALIDATE will fail
     *
     * @throws RuntimeException
     */
    public function requiredParameterCheck(array $params): void
    {
        foreach ($params as $key => $type) {
            if (!array_key_exists($key, $this->params)) {
                throw new RuntimeException("Param ${key} not set", 1545654109);
            }
        }
        $this->optionalParameterCheck($params);
    }

    /**
     * @param array $params array formated like <parameter name> => <type>
     *
     * NOTE: Only call this with SANITIZE Filters, VALIDATE will fail
     *
     * @throws RuntimeException
     *
     * TODO: Properly filter Params
     */
    public function optionalParameterCheck(array $params): void
    {
        foreach ($params as $key => $type) {
            if (!array_key_exists($key, $this->params)) {
                break;
            }

            if (!is_array($type)) {
                $type = [$type];
            }
            foreach ($type as $currentType) {
                $test = filter_var($this->params[$key], $currentType);
                if (false === $test) {
                    throw new RuntimeException("Param ${key} resulted in filter error", 1545654117);
                }
                if ($test !== $this->params[$key]) {
                    throw new RuntimeException("Param ${key} has invalid characters", 1545654122);
                }
            }
        }
    }
}
