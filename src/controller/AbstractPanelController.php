<?php

namespace Helio\Panel\Controller;

use Ergy\Slim\Annotations\Controller;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

/**
 * Abstract Panel Controller
 *
 * @property PhpRenderer renderer
 * @property Response response
 * @property Request request
 * @property Logger logger
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 */
abstract class AbstractPanelController extends Controller
{


    /**
     * @var string
     */
    protected $zapierHookUrl;


    /**
     * AbstractPanelController constructor.
     *
     * @throws \RuntimeException
     */
    public function __construct()
    {
        if (!array_key_exists('ZAPIER_HOOK_URL', $_SERVER)) {
            throw new \RuntimeException('Zapier hook URL not set');
        }
        $this->zapierHookUrl = $_SERVER['ZAPIER_HOOK_URL'];
    }


    /**
     * @param $name
     *
     * @return ResponseInterface
     */
    abstract public function TestAction($name): ResponseInterface;
}