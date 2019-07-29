<?php

namespace Helio\Panel\Controller;

use \RuntimeException;

use Ergy\Slim\Annotations\Controller;
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

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Utility\ServerUtility;

use function OpenApi\scan;

/**
 * Abstract Panel Controller
 *
 * @property PhpRenderer renderer
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
                    throw new RuntimeException('Controller Setup failed: ' . $method, 1551432903);
                }
            }
        }

        // then: validate everything
        foreach (get_class_methods($this) as $method) {
            if (strpos($method, 'validate') === 0) {
                if (!$this->$method()) {
                    throw new RuntimeException('Controller Validation failed: ' . $method, 1551432915);
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
        if (array_key_exists('impersonate', $this->request->getCookieParams())) {
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
        if ($status > 299) {
            LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data, true) . "\nRequest:\n" . print_r((string)$this->request->getBody(), true));
        }
        if (array_key_exists('message', $data)) {
            $data['notification'] = $this->fetchPartial('message', [
                'message' => $data['message'],
                'status' => $data['status'] ?? 'ok',
                'success' => $data['success'] ?? 'success'
            ]);
        }
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
            if ($status > 299) {
                LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data, true) . "\nRequest:\n" . print_r((string)$this->request->getBody(), true));
            }

            $this->response->getBody()->write($data);
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
        if ($status > 299) {
            LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data, true) . "\nRequest:\n" . print_r((string)$this->request->getBody(), true));
        }

        if (is_array($data)) {
            $data = Yaml::dump($data, 4, 2);
        }
        return $this->rawYaml($data, $status);
    }


    /**
     * @param string $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function rawYaml(string $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        if ($status > 299) {
            LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data, true) . "\nRequest:\n" . print_r((string)$this->request->getBody(), true));
        }

        $this->response->getBody()->write($data);
        return $this->response
            ->withHeader('Content-Type', 'application/x-yaml')
            ->withStatus($status);
    }


    /**
     * @param array|string $include array of filenames or regex of filenames to include
     * @return ResponseInterface
     *
     */
    protected function renderApiDocumentation($include = []): ResponseInterface
    {
        $path = ServerUtility::getClassesPath();
        $exclude = [];

        // unfourtunately, OpenApi::scan() only has an exclude functionality, so we need to "invert"  $include
        if ($include) {
            if (is_array($include) && count($include) === 1) {
                $path .= DIRECTORY_SEPARATOR . $include[0];
            } else {
                $exclude = array_filter(ServerUtility::getAllFilesInFolder($path, '.php'), function ($object) use ($include, $path) {
                    $filenameInsidePath = substr($object, strlen($path . DIRECTORY_SEPARATOR));

                    return
                        (is_array($include) && !in_array($filenameInsidePath, $include, true))
                        ||
                        (is_string($include) && preg_match($include, $object) === 0);
                });
            }
        }
        $openapi = scan($path, ['exclude' => $exclude]);

        if ((array_key_exists('format', $this->params) && $this->params['format'] === 'json')
            || $this->request->getHeader('Content-Type') === 'application/json') {
            return $this->rawJson($openapi->toJson());
        }

        return $this->rawYaml($openapi->toYaml());
    }
}