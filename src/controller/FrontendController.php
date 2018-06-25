<?php

namespace Helio\Panel\Controller;

use Psr\Http\Message\ResponseInterface;


/**
 * Class Frontend
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/')
 */
class FrontendController extends AbstractPanelController
{


    /**
     * @param $name
     *
     * @return ResponseInterface
     *
     * @Route("hello/{name:[\w]+}", methods={"GET"}, name="home.hello")
     */
    public function TestAction($name): ResponseInterface
    {
        $this->logger->addNotice('entered frontendController->HelloAction');
        return $this->renderer->render($this->response,
            'index.phtml',
            array_merge_recursive(['name' => $name], $this->request->getParams())
        );
    }

}