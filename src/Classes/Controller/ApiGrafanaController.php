<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Controller\Traits\AuthenticatedController;
use Helio\Panel\Controller\Traits\GoogleAuthenticatedController;
use Helio\Panel\Controller\Traits\TypeBrowserController;
use Psr\Http\Message\ResponseInterface;


/**
 * Class ApiGrafanaController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/grafana')
 *
 */
class ApiGrafanaController extends AbstractController
{
    use AuthenticatedController;
    use TypeBrowserController;
    use GoogleAuthenticatedController;

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = 'https://grafana.idling.host';
    }

    protected function getMode(): ?string
    {
        return 'passthrough';
    }

    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"GET"}, name="api.grafana")
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function snapshotAction(string $path = 'alwJATHSkNmMyG7FeJNTidJYD3xhsN07?orgId=0&panelId=77&from=1545218855263&to=1545220655264&var-job=node_manager1&var-node=manager1&var-port=9100&var-user=undefined&var-server=undefined'): ResponseInterface
    {

        $resultFromGoogle = $this->requestIapProtectedResource('/dashboard-solo/snapshot/' . $path);

        return $this->response->write(str_replace(['"public/', 'appSubUrl":""'], ['"api/grafana/public/', 'appSubUrl":"/api/grafana"'], preg_replace('/(<)(?!base)(([^>])*)(src|href)=(\'|")\//', '$1$2$3$4=$5$6/api/grafana/', $resultFromGoogle->getBody()->getContents())))->withHeader('Content-Type', $resultFromGoogle->getHeader('Content-Type'));

    }

    /**
     * @param $path
     * @return ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @Route("/{path:.+}", methods={"GET"}, name="api.grafana.assets")
     */
    public function assetsAction($path): ResponseInterface
    {
        return $this->requestIapProtectedResource('/' . $path)->withoutHeader('Transfer-Encoding');
    }
}