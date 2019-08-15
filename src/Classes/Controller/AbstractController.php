<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Response\AbstractResponse;
use Helio\Panel\Response\DefaultResponse;
use OpenApi\Annotations as OA;
use Ergy\Slim\Annotations\RouteInfo;
use RuntimeException;
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
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Utility\ServerUtility;
use function OpenApi\scan;

/**
 * Abstract Controller.
 *
 *
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="authByApitoken",
 *     description="The API Token of your user, obtainable in the WebUI at panel.idling.host"
 * )
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="authByJobtoken",
 *     description="The Job specific token received during /api/job/add"
 * )
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="authByInstancetoken",
 *     description="The Instance specific token received during registering an instance"
 * )
 *
 ***************************** Schemas for Re-Use
 *
 * @OA\RequestBody(
 *     request="job",
 *     description="Job Settings in Body or Query String",
 *     @OA\JsonContent(ref="#/components/schemas/Job")
 * )
 *
 * @OA\Schema(
 *     schema="logentry",
 *     description="Log entry",
 *     type="object",
 *     @OA\Property(
 *         property="@timestamp",
 *         description="Timestamp of the entry",
 *         type="number"
 *     ),
 *     @OA\Property(
 *         property="log",
 *         description="Log Entry",
 *         type="string"
 *     )
 * )
 * @OA\Schema(
 *     schema="logs",
 *     description="List of Log entries",
 *     type="object",
 *     @OA\Property(
 *         property="total",
 *         description="Total amount of hits",
 *         type="integer"
 *     ),
 *     @OA\Property(
 *         type="array",
 *         property="hits",
 *         @OA\Items(
 *             @OA\Property(
 *                 property="_source",
 *                 @OA\Items(ref="#/components/schemas/logentry")
 *             )
 *         )
 *     )
 * )
 * @OA\Schema(
 *     schema="estimates",
 *     description="Estimates about the current execution",
 *     type="object",
 *     @OA\Property(
 *         property="duration",
 *         description="Estimated duration of the job",
 *         type="integer"
 *     ),
 *     @OA\Property(
 *         property="completion",
 *         description="Estimated completion Timestamp",
 *         type="integer"
 *     ),
 *     @OA\Property(
 *         property="cost",
 *         description="Estimated cost in USD",
 *         type="integer"
 *     )
 * )
 *
 * @OA\Response(
 *     response="200",
 *     description="Default success response.",
 *     @OA\JsonContent(
 *         @OA\Schema(ref="#/components/schemas/DefaultResponse")
 *     )
 * )
 * @OA\Response(
 *     response="404",
 *     description="Resource not found"
 * )
 * @OA\Response(
 *     response="405",
 *     description="You're not authorized to access this resource",
 *     @OA\JsonContent(
 *         @OA\Schema(ref="#/components/schemas/DefaultResponse")
 *     )
 * )
 * @OA\Response(
 *     response="406",
 *     description="You specified invalid parameters",
 *     @OA\JsonContent(
 *         @OA\Schema(ref="#/components/schemas/DefaultResponse")
 *     )
 * )
 * @OA\Response(
 *     response="500",
 *     description="Server Error or Exception.",
 *     @OA\JsonContent(
 *         @OA\Schema(ref="#/components/schemas/DefaultResponse")
 *     )
 * )
 * @OA\Response(
 *     response="logs",
 *     description="Log Entries and total count",
 *     @OA\JsonContent(ref="#/components/schemas/logs")
 * )
 *
 * @property PhpRenderer renderer
 * @property array settings
 * @property EnvironmentInterface environment
 * @property Request request
 * @property Response response
 * @property RouterInterface router
 * @property InvocationStrategyInterface foundHandler
 * @property callable errorHandler
 * @property callable notFoundHandler
 * @property callable notAllowedHandler
 * @property CallableResolverInterface callableResolver
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
abstract class AbstractController extends Controller
{
    /**
     * The return type is used to determine what language the client understands (e.g. json, html, ...).
     *
     * @return string
     */
    abstract protected function getReturnType(): ?string;

    /**
     * The mode is used to deremine where to look for templates.
     *
     * @return string
     */
    abstract protected function getMode(): ?string;

    /**
     * The Context is used to determine where to look for templates of PARTIARLS!
     * This is usually just the same as the mode, but can be different if an API endpoint that renders partials is used from different places.
     * In that case, overwrite this method in such controllers.
     *
     * @return string|null
     */
    protected function getContext(): ?string
    {
        return $this->getMode();
    }

    /**
     * @return string $idAlias if a param named 'id' is set, what does it stand for?
     */
    protected function getIdAlias(): string
    {
        return '';
    }

    /**
     * magic method to prepare your controllers (e.g. use it with traits)
     * Warning: carefully name your methods.
     *
     * @param RouteInfo $route
     */
    public function beforeExecuteRoute(RouteInfo $route): void
    {
        // first: setup everything
        foreach (get_class_methods($this) as $method) {
            if (0 === strpos($method, 'setup')) {
                if (!$this->$method($route)) {
                    throw new RuntimeException('Controller Setup failed: ' . $method, 1551432903);
                }
            }
        }

        // then: validate everything
        foreach (get_class_methods($this) as $method) {
            if (0 === strpos($method, 'validate')) {
                if (!$this->$method()) {
                    throw new RuntimeException('Controller Validation failed: ' . $method, 1551432915);
                }
            }
        }
    }

    /**
     * @param string $partial
     * @param array  $param
     *
     * @return string
     */
    protected function fetchPartial(string $partial, array $param = []): string
    {
        return $this->renderer->fetch($this->getContext() . "/partial/${partial}.phtml", $param);
    }

    /**
     * @param AbstractResponse $response
     * @param int   $status
     *
     * @return ResponseInterface
     */
    protected function render(AbstractResponse $response, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        $method = $this->getReturnType();

        return $this->$method($response, $status);
    }

    /**
     * @param $data
     * @param int $status
     *
     * @return ResponseInterface
     */
    protected function html(AbstractResponse $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        $data = $data->toArray();
        if (array_key_exists('impersonate', $this->request->getCookieParams())) {
            $data['impersonating'] = true;
        }

        return $this->renderer->render(
            $this->response,
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
    protected function json(AbstractResponse $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        if ($status > 299) {
            LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data->toArray(), true) . "\nRequest:\n" . print_r((string) $this->request->getBody(), true));
        }
        if ($data instanceof DefaultResponse && isset($data->message)) {
            $data->setNotification($this->fetchPartial('message', [
                'message' => $data['message'],
                'status' => $data['status'] ?? 'ok',
                'success' => $data['success'] ?? 'success',
            ]));
        }

        return $this->response->withJson($data)->withStatus($status);
    }

    /**
     * @param string $data
     * @param int    $status
     *
     * @return ResponseInterface
     */
    protected function rawJson(string $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        if ($status > 299) {
            LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data, true) . "\nRequest:\n" . print_r((string) $this->request->getBody(), true));
        }

        $this->response->getBody()->write($data);

        return $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
    }

    /**
     * @param $data
     * @param int $status
     *
     * @return ResponseInterface
     */
    protected function yaml(AbstractResponse $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        $data = $data->toArray();
        if ($status > 299) {
            LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data, true) . "\nRequest:\n" . print_r((string) $this->request->getBody(), true));
        }

        if (is_array($data)) {
            $data = Yaml::dump($data, 4, 2);
        }

        return $this->rawYaml($data, $status);
    }

    /**
     * @param string $data
     * @param int    $status
     *
     * @return ResponseInterface
     */
    protected function rawYaml(string $data, int $status = StatusCode::HTTP_OK): ResponseInterface
    {
        if ($status > 299) {
            LogHelper::warn('API error on ' . $this->request->getUri() . ' with code ' . $status . "\nResponse Data:\n" . print_r($data, true) . "\nRequest:\n" . print_r((string) $this->request->getBody(), true));
        }

        $this->response->getBody()->write($data);

        return $this->response
            ->withHeader('Content-Type', 'application/x-yaml')
            ->withStatus($status);
    }

    /**
     * @param array|string $include array of filenames or regex of filenames to include
     *
     * @return ResponseInterface
     */
    protected function renderApiDocumentation($include = []): ResponseInterface
    {
        $path = ServerUtility::getClassesPath();
        $exclude = [];

        // unfourtunately, OpenApi::scan() only has an exclude functionality, so we need to "invert"  $include
        if ($include) {
            if (is_array($include) && 1 === count($include)) {
                $path .= DIRECTORY_SEPARATOR . $include[0];
            } else {
                $exclude = array_filter(ServerUtility::getAllFilesInFolder($path, '.php'), function ($object) use ($include, $path) {
                    $filenameInsidePath = substr($object, strlen($path . DIRECTORY_SEPARATOR));

                    return
                        (is_array($include) && !in_array($filenameInsidePath, $include, true))
                        ||
                        (is_string($include) && 0 === preg_match($include, $object));
                });
            }
        }
        $openapi = scan($path, ['exclude' => $exclude]);

        if (('json' === $this->request->getParam('format', 'yaml'))
            || 'application/json' === $this->request->getHeader('Content-Type')) {
            return $this->rawJson($openapi->toJson());
        }

        return $this->rawYaml($openapi->toYaml());
    }
}
