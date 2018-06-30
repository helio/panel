<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Helper\CookieHelper;
use Helio\Panel\Helper\JwtHelper;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PanelController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/panel')
 */
class PanelController extends AbstractController
{


    /**
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     *
     * @Route("", methods={"GET"}, name="panel.index")
     */
    public function indexAction(): ResponseInterface
    {

        return $this->render('panel/index');
    }
}