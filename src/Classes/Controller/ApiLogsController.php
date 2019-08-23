<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\Controller\Traits\ModelUserController;
use Helio\Panel\Controller\Traits\ModelParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Helper\ElasticHelper;
use Helio\Panel\Request\Log;
use Helio\Panel\Service\LogService;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/logs')
 */
class ApiLogsController extends AbstractController
{
    use ModelUserController;
    use ModelParametrizedController;
    use TypeApiController;

    /**
     * @var LogService
     */
    private $logService;

    public function __construct()
    {
        $this->logService = new LogService(ElasticHelper::getInstance());
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("", methods={"GET"}, name="logs")
     */
    public function logsAction(): ResponseInterface
    {
        $requestParams = Log::fromParams($this->params);
        $data = $this->logService->retrieveLogs($this->user->getId(), $requestParams, -1, -1);

        return $this->render($data);
    }
}
