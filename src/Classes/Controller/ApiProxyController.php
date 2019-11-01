<?php

namespace Helio\Panel\Controller;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\App;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;

class ApiProxyController extends AbstractController
{
    /**
     * @var ClientInterface
     */
    private $storageClient;

    /**
     * @var ClientInterface
     */
    private $analyzeClient;

    /**
     * @var ClientInterface
     */
    private $billingClient;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Configured envs -> baseURI for storage service
     * FIXME(mw): going further: we need proper config if we keep this codebase. But we might want to change this anyway:
     *            use a proper API gateway instead.
     */
    private const STORAGE_API_BASE_URIS = [
        'local' => 'http://host.docker.internal:8080',
        'prod' => 'https://storage-service.helio.dev',
    ];

    /**
     * Configured envs -> baseURI for analyze service
     * FIXME(mw): going further: we need proper config if we keep this codebase. But we might want to change this anyway:
     *            use a proper API gateway instead.
     */
    private const ANALYZE_API_BASE_URIS = [
        'local' => 'http://host.docker.internal:8081',
        'prod' => 'https://analyze.helio.dev',
    ];

    /**
     * Configured envs -> baseURI for billing service
     * FIXME(mw): going further: we need proper config if we keep this codebase. But we might want to change this anyway:
     *            use a proper API gateway instead.
     */
    private const BILLING_API_BASE_URIS = [
        'local' => 'http://host.docker.internal:8082',
        'prod' => 'https://billing.helio.dev',
    ];

    public function __construct()
    {
        $this->storageClient = $this->configureClient('STORAGE_SERVICE_ENV', self::STORAGE_API_BASE_URIS);
        $this->analyzeClient = $this->configureClient('ANALYZE_SERVICE_ENV', self::ANALYZE_API_BASE_URIS);
        $this->billingClient = $this->configureClient('BILLING_SERVICE_ENV', self::BILLING_API_BASE_URIS);
        $this->container = App::getApp()->getContainer();
    }

    /**
     * @param  string            $path
     * @return ResponseInterface
     *
     * @Route("/api/storage/{path:.*}", methods={"GET", "PUT", "POST"}, name="storage.service")
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function proxyStorageAction(string $path): ResponseInterface
    {
        $response = $this->proxyRequest($this->request, $path, $this->storageClient);
        $res = $this->createProxiedResponse($response);

        return $res;
    }

    /**
     * @param  string            $path
     * @return ResponseInterface
     *
     * @Route("/api/analyze/{path:.*}", methods={"GET"}, name="analyze.service")
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function proxyAnalyzeAction(string $path): ResponseInterface
    {
        $response = $this->proxyRequest($this->request, $path, $this->analyzeClient);
        $res = $this->createProxiedResponse($response);

        return $res;
    }

    /**
     * @param  string            $path
     * @return ResponseInterface
     *
     * @Route("/api/billing/{path:.*}", methods={"POST"}, name="billing.service")
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function proxyBillingAction(string $path): ResponseInterface
    {
        $response = $this->proxyRequest($this->request, $path, $this->billingClient);
        $res = $this->createProxiedResponse($response);

        return $res;
    }

    protected function getReturnType(): string
    {
        return 'json';
    }

    protected function getMode(): string
    {
        return 'api';
    }

    /**
     * @param RequestInterface $request
     * @param string           $path
     * @param ClientInterface  $client
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    private function proxyRequest(RequestInterface $request, string $path, ClientInterface $client): ResponseInterface
    {
        /** @var User $user */
        $user = $this->container->get('user');

        $headers = [
            'Helio-User-Id' => $user->getId(),
            'Request-Id' => $this->container->get('requestId'),
        ];
        foreach (['Content-Type', 'Content-Length', 'Content-Range', 'X-Upload-Content-Type', 'X-Upload-Content-Length'] as $headerName) {
            if ($request->hasHeader($headerName) && strlen($request->getHeader($headerName)[0])) {
                $headers[$headerName] = $request->getHeader($headerName);
            }
        }

        // >:( PHP API wtf #20348576
        parse_str($request->getUri()->getQuery(), $query);

        return $client->request(
            $request->getMethod(),
            $path,
            [
                'headers' => $headers,
                'body' => $request->getBody(),
                'query' => array_filter($query, function ($k) {
                    return 'token' != $k;
                }, ARRAY_FILTER_USE_KEY),
            ]
        );
    }

    /**
     * @param string $envName
     * @param array  $baseUriMap
     *
     * @return ClientInterface
     *
     * @throws Exception
     */
    private function configureClient(string $envName, array $baseUriMap): ClientInterface
    {
        $env = strtolower(ServerUtility::get($envName, 'prod'));
        $baseUri = $baseUriMap[$env];
        if (!$baseUri) {
            throw new \Exception(sprintf('Unable to find baseURI for analyze service env "%s". Configure it in %s', $env, __CLASS__));
        }

        return new Client([
            'base_uri' => $baseUri,
            'connect_timeout' => 30,
            'read_timeout' => 500,
            'stream' => true,
            'timeout' => 300,
            'http_errors' => false,
            'proxy' => ServerUtility::getProxySettings(),
        ]);
    }

    protected function createProxiedResponse(ResponseInterface $response): Response
    {
        $res = $this->response->withBody($response->getBody())->withStatus($response->getStatusCode());

        foreach (['Content-Type', 'Content-Encoding', 'Cache-Control', 'Content-Disposition', 'Etag', 'Request-Id'] as $headerName) {
            if ($response->hasHeader($headerName)) {
                $res = $res->withHeader($headerName, $response->getHeader($headerName));
            }
        }

        return $res;
    }
}
