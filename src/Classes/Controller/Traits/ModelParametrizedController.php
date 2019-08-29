<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Helio\Panel\Exception\HttpException;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\StatusCode;

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
            $body = null;
            if ('application/json' === $this->request->getMediaType() && $this->request->getBody()->getSize()) {
                try {
                    $body = \GuzzleHttp\json_decode($this->request->getBody(), true);
                } catch (\InvalidArgumentException $e) {
                    throw new HttpException(StatusCode::HTTP_BAD_REQUEST, $e->getMessage(), $e);
                }
            }
            $this->params = array_merge($body ?: [], $this->request->getParams() ?? [], $route->params ?? []);
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
                throw new HttpException(StatusCode::HTTP_BAD_REQUEST, "Param ${key} not set", null, 1545654109);
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
                    throw new HttpException(StatusCode::HTTP_BAD_REQUEST, "Param ${key} resulted in filter error", null, 1545654109);
                }
                if ($test !== $this->params[$key]) {
                    throw new HttpException(StatusCode::HTTP_BAD_REQUEST, "Param ${key} has invalid characters", null, 1545654109);
                }
            }
        }
    }
}
