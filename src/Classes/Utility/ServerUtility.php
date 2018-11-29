<?php

namespace Helio\Panel\Utility;

use Helio\Panel\Model\Server;

class ServerUtility
{

    /**
     * @var string
     */
    public static $timeZone = 'Europe/Berlin';


    private static $autosignCommand = 'ssh panel@35.198.151.151 "autosign generate -b %s"';

    private static $startComputingCommand = 'ssh panel@35.198.167.207 "sudo docker node update --availability active %s"';
    private static $stopComputingCommand = 'ssh panel@35.198.167.207 "sudo docker node update --availability drain  %s"';


    /**
     *
     * @return string
     */
    public static function getBaseUrl(): string
    {

        $protocol = 'http' . (self::isSecure() ? 's' : '');

        return $protocol . '://' . self::get('HTTP_HOST') . '/';
    }


    /**
     * @return bool
     */
    public static function isSecure(): bool
    {
        if (self::get('HTTPS', 'off') !== 'off') {
            return true;
        }
        if (self::isBehindReverseProxy()) {
            return self::get('HTTTP_X_FORWARDED_PROTO', false) === 'https';
        }
        return false;
    }


    /**
     * @param string $name
     * @param mixed|null $default
     *
     * @return string
     */
    public static function get(string $name, $default = null): string
    {

        if (!array_key_exists($name, $_SERVER) || !$_SERVER[$name]) {
            if ($default) {
                return $default;
            }
            throw new \RuntimeException('please set the ENV Variable ' . $name, 1530357047);
        }

        return $_SERVER[$name];
    }


    /**
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        return self::isBehindReverseProxy() ? self::get('HTTP_X_FORWARDED_FOR') : self::get('REMOTE_ADDR');
    }


    /**
     * @return bool
     */
    public static function isBehindReverseProxy(): bool
    {
        return self::get('REMOTE_ADDR') === self::get('REVERSE_PROXY_IP', 'impossible') && self::get('HTTP_X_FORWARDED_FOR', false);
    }


    /**
     * @param string $fqdn
     * @param bool $returnInsteadOfCall
     *
     * @return string
     */
    public static function submitAutosign(string $fqdn, bool $returnInsteadOfCall = false): string
    {
        $match = preg_match('/[^0-9a-zA-Z\.\-_]/', $fqdn);
        if ($match !== 0) {
            throw new \InvalidArgumentException('invalid fqdn submitted for autosign', 1531076419);
        }

        $command = sprintf(self::$autosignCommand, $fqdn);

        return $returnInsteadOfCall ? $command : shell_exec($command);
    }


    /**
     * @param Server $server
     * @param bool $returnInsteadOfCall
     *
     * @return string
     */
    public static function submitStartRequest(Server $server, bool $returnInsteadOfCall = false): string
    {
        $match = preg_match('/[^0-9a-zA-Z\.\-_]/', $server->getFqdn());
        if ($match !== 0) {
            throw new \InvalidArgumentException('invalid fqdn submitted for startRequest', 1533592106);
        }

        $command = sprintf(self::$startComputingCommand, $server->getFqdn());

        return $returnInsteadOfCall ? $command : shell_exec($command);
    }


    /**
     * @param Server $server
     * @param bool $returnInsteadOfCall
     *
     * @return string
     */
    public static function submitStopRequest(Server $server, bool $returnInsteadOfCall = false): string
    {
        $match = preg_match('/[^0-9a-zA-Z\.\-_]/', $server->getFqdn());
        if ($match !== 0) {
            throw new \InvalidArgumentException('invalid fqdn submitted for stopRequest', 1533592106);
        }

        $command = sprintf(self::$stopComputingCommand, $server->getFqdn());

        return $returnInsteadOfCall ? $command : shell_exec($command);
    }
}
