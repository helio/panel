<?php

namespace Helio\Panel\Controller;

use Ergy\Slim\Annotations\Controller;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\ZapierHelper;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\Http\EnvironmentInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RouterInterface;
use Slim\Views\PhpRenderer;

/**
 * Abstract Panel Controller
 *
 * @property PhpRenderer renderer
 * @property Logger logger
 * @property DbHelper dbHelper
 * @property array jwt
 * @property ZapierHelper zapierHelper
 *
 * @property-read array settings
 * @property-read EnvironmentInterface environment
 * @property-read Request request
 * @property-read Response response
 * @property-read RouterInterface router
 * @property-read InvocationStrategyInterface foundHandler
 * @property-read callable errorHandler
 * @property-read callable notFoundHandler
 * @property-read callable notAllowedHandler
 * @property-read CallableResolverInterface callableResolver
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 */
abstract class AbstractController extends Controller
{


    /**
     * @param string $template
     * @param array $params
     * @param int $status
     *
     * @return ResponseInterface
     */
    protected function render(string $template, array $params = [], int $status = 200): ResponseInterface
    {
        if (!$template) {
            throw new \InvalidArgumentException('No template specified', 1530051401);
        }
        $params = array_merge_recursive($params, ['childTemplate' => $template], $this->request->getParams());

        return $this->renderer->render($this->response,
            'index.phtml',
            $params
        )->withStatus($status);
    }


    /**
     * @param $data
     * @param int $status
     *
     * @return ResponseInterface
     */
    protected function json($data, int $status = 200): ResponseInterface
    {
        return $this->response->withJson($data)->withStatus($status);
    }
}