<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Controller\Traits\ParametrizedController;
use Helio\Panel\Controller\Traits\StatisticsController;
use Helio\Panel\Controller\Traits\TypeBrowserController;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\CookieUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class PanelController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/panel')
 *
 */
class PanelController extends AbstractController
{

    use ParametrizedController;
    use StatisticsController;
    use TypeBrowserController;

    protected function getMode(): ?string
    {
        return 'panel';
    }

    /**
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     *
     * @Route("", methods={"GET", "POST"}, name="panel.index")
     */
    public function indexAction(): ResponseInterface
    {
        return $this->render([
            'serverByRegion' => $this->statServerByRegion(),
            'user' => $this->user,
            'title' => 'Dashboard - Helio Panel',
            'dashboardActive' => 'active',
            'partialJs' => ['donutChart']
        ]);

    }

    /**
     * @return ResponseInterface
     *
     * @Route("/buy", "methods={"GET", "POST"}, name="panel.buy")
     */
    public function BuyAction(): ResponseInterface
    {
        return $this->render([
            'user' => $this->user,
            'title' => 'Your Jobs - Helio Panel',
            'buyActive' => 'active',
            'module' => 'buy',
            'partialJs' => ['jobList'],
            'modalTemplates' => ['addJob']
        ]);

    }

    /**
     * @return ResponseInterface
     *
     * @Route("/sell", methods={"GET"}, name="panel.sell")
     */
    public function SellAction(): ResponseInterface
    {
        return $this->render([
            'user' => $this->user,
            'title' => 'Your Servers - Helio Panel',
            'sellActive' => 'active',
            'module' => 'sell',
            'partialJs' => ['instanceList'],
            'modalTemplates' => ['addInstance']
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/profile", methods={"GET"}, name="user.profile")
     */
    public function profileAction(): ResponseInterface
    {
        return $this->render([
            'user' => $this->user,
            'title' => 'Your Profile Page - Helio Panel',
            'module' => 'profile',
            'profileActive' => 'active',
            'partialJs' => ['profile']
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/admin", methods={"GET"}, name="user.admin")
     */
    public function adminAction(): ResponseInterface
    {
        if (!$this->user->isAdmin()) {
            return $this->response->withRedirect('/panel', StatusCode::HTTP_FOUND);
        }
        return $this->render([
            'users' => $this->dbHelper->getRepository(User::class)->findAll(),
            'user' => $this->user,
            'title' => 'Admin - Helio Panel',
            'module' => 'admin',
            'adminActive' => 'active',
            'partialJs' => ['admin']
        ]);
    }

    /**
     * Note: This has to be here becuase in the "user" module, we don't have the jwt information since that section isn't protected.
     *
     * @return ResponseInterface
     *
     * @Route("/logout", methods={"GET"}, name="user.logout")
     * @throws \Exception
     */
    public function LogoutUserAction(): ResponseInterface
    {
        $this->user->setLoggedOut();
        $this->dbHelper->persist($this->user);
        $this->dbHelper->flush($this->user);

        return CookieUtility::deleteCookie($this->response->withRedirect('/loggedout', StatusCode::HTTP_FOUND), 'token');
    }
}