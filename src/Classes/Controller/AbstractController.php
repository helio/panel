<?php

namespace Helio\Panel\Controller;

use Ergy\Slim\Annotations\Controller;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Helper\ZapierHelper;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\Http\EnvironmentInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RouterInterface;
use Slim\Views\PhpRenderer;
use Symfony\Component\Yaml\Yaml;

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
     * The return type is used to determine what language the client understands (e.g. json, html, ...)
     *
     * @return string
     */
    abstract protected function getReturnType(): ?string;


    /**
     * The mode is used to deremine where to look for templates
     *
     * @return string
     */
    abstract protected function getMode(): ?string;


    /**
     * The Context is used to determine where to look for templates of PARTIARLS!
     * This is usually just the same as the mode, but can be different if an API endpoint that renders partials is used from different places.
     * In that case, overwrite this method in such controllers.
     *
     * @return null|string
     */
    protected function getContext(): ?string
    {
        return $this->getMode();
    }


    /**
     * magic method to prepare your controllers (e.g. use it with traits)
     * Warning: carefully name your methods
     *
     */
    public function __construct()
    {
        // first: setup everything
        foreach (get_class_methods($this) as $method) {
            if (strpos($method, 'setup') === 0) {
                if (!$this->$method()) {
                    throw new \RuntimeException('Controller Setup failed: ' . $method, 1551432903);
                }
            }
        }

        // then: validate everything
        foreach (get_class_methods($this) as $method) {
            if (strpos($method, 'validate') === 0) {
                if (!$this->$method()) {
                    throw new \RuntimeException('Controller Validation failed: ' . $method, 1551432915);
                }
            }
        }
    }


    /**
     * @param string $partial
     * @param array $param
     * @return string
     */
    protected function fetchPartial(string $partial, array $param = []): string
    {
        return $this->renderer->fetch($this->getContext() . "/partial/${partial}.phtml", $param);
    }


    /**
     * @param array $params
     * @param int $status
     *
     * @return ResponseInterface
     */
    protected function render(array $params = [], int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        $method = $this->getReturnType();
        return $this->$method($params, $status);
    }

    /**
     * @param $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function html($data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        if (\array_key_exists('impersonate', $this->request->getCookieParams())) {
            $data['impersonating'] = true;
        }

        return $this->renderer->render($this->response,
            $this->getMode() . '/index.phtml',
            $data
        )->withStatus($status);
    }


    /**
     * @param $data
     * @param int $status
     *
     * @return ResponseInterface
     */
    protected function json($data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        if (\array_key_exists('message', $data)) {
            $data['notification'] = $this->fetchPartial('message', [
                'message' => $data['message'],
                'status' => $data['status'] ?? 'ok',
                'success' => $data['success'] ?? 'success'
            ]);
        }
        LogHelper::debug('API answer with code ' . $status . "\n" . print_r($data, true));
        return $this->response->withJson($data)->withStatus($status);
    }

    /**
     * @param string $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function rawJson(string $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        {
            $this->response->getBody()->write($data);
            LogHelper::debug('API answer with code ' . $status . "\n" . print_r($data, true));

            return $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
        }
    }


    /**
     * @param $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function yaml($data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {

        if (\is_array($data)) {
            $data = Yaml::dump($data, 4, 2);
        }
        LogHelper::debug('API answer with code ' . $status . "\n" . print_r($data, true));
        return $this->rawYaml($data, $status);
    }


    /**
     * @param string $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function rawYaml(string $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        $this->response->getBody()->write($data);
        LogHelper::debug('API answer with code ' . $status . "\n" . print_r($data, true));
        return $this->response
            ->withHeader('Content-Type', 'application/x-yaml')
            ->withStatus($status);

    }
}