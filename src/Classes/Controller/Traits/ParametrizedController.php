<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\User;
use Slim\Http\Request;

/**
 * Trait ParametrizedController
 * @package Helio\Panel\Controller\Traits
 *
 * @property array params
 */
trait ParametrizedController
{

    /**
     * @var User
     */
    protected $params;

            /** @var Request request */

    /**
     * @return bool
     */
    public function setupParams(): bool
    {
        if (!$this->params) {
            $this->params = $this->request->getParams();
        }
        return true;
    }

    /**
     * @param array $params Array formated like <parameter name> => <type>
     *
     * NOTE: Only call this with SANITIZE Filters, VALIDATE will fail.
     *
     * @throws \RuntimeException
     */
    public function requiredParameterCheck(array $params): void
    {
        foreach ($params as $key => $type) {
            if (!array_key_exists($key, $this->params)) {
                throw new \RuntimeException("Param ${key} not set", 1545654109);
            }
        }
        $this->optionalParameterCheck($params);
    }

    /**
     * @param array $params Array formated like <parameter name> => <type>
     *
     * NOTE: Only call this with SANITIZE Filters, VALIDATE will fail.
     *
     * @throws \RuntimeException
     */
    public function optionalParameterCheck(array $params): void
    {
        foreach ($params as $key => $type) {
            if (!array_key_exists($key, $this->params)) {
                break;
            }

            if (!\is_array($type)) {
                $type = [$type];
            }
            foreach ($type as $currentType) {
                $test = filter_var($this->params[$key], $currentType);
                if ($test === false) {
                    throw new \RuntimeException("Param ${key} resulted in filter error", 1545654117);
                }
                if ($test !== $this->params[$key]) {
                    throw new \RuntimeException("Param ${key} has invalid characters", 1545654122);
                }
            }
        }
    }
}