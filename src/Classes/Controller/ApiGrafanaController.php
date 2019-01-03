<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Controller\Traits\AuthenticatedController;
use Helio\Panel\Controller\Traits\TypeBrowserController;
use Helio\Panel\Helper\GoogleIapHelper;
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


    protected function getMode(): ?string
    {
        return 'passthrough';
    }

    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"GET"}, name="server.stop")
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testAction(): ResponseInterface
    {
        $auth = new GoogleIapHelper();
        return $auth->make_iap_request(
            'https://grafana.idling.host/dashboard-solo/snapshot/alwJATHSkNmMyG7FeJNTidJYD3xhsN07?orgId=0&panelId=77&from=1545218855263&to=1545220655264&var-job=node_manager1&var-node=manager1&var-port=9100&var-user=undefined&var-server=undefined'
        );
    }
}