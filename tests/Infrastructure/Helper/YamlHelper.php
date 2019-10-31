<?php

namespace Helio\Test\Infrastructure\Helper;

use Helio\Panel\Utility\ArrayUtility;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Yaml;

class YamlHelper
{
    /**
     * @param ResponseInterface $response
     * @param string            $key
     *
     * @return mixed|string
     */
    public static function findValueOfKeyInHiera(ResponseInterface $response, string $key)
    {
        $hiera = Yaml::parse((string) $response->getBody());

        return ArrayUtility::getFirstByDotNotation([$hiera], ['profile::docker::clusters.' . $key]);
    }

    /**
     * @param ResponseInterface $response
     * @param string            $key
     * @param string            $name
     *
     * @return mixed|string
     */
    public static function findEnvElementOfArrayInHiera(ResponseInterface $response, string $key, string $name)
    {
        $hiera = Yaml::parse((string) $response->getBody());
        foreach (ArrayUtility::getFirstByDotNotation([$hiera], ['profile::docker::clusters.' . $key], []) as $env) {
            if (false !== strpos($env, $name)) {
                $matches = [];
                preg_match("/$name=\s*([^'$]+)/", (string) $response->getBody(), $matches);

                return $matches[1];
            }
        }

        return '';
    }
}
