<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\ModelUserController;
use Helio\Panel\Controller\Traits\TypeBrowserController;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Service\UserService;
use Helio\Panel\Utility\CookieUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class PanelController
 * Controller taking care of the actions serving the Panel HTML. This controller requiers an authenticated user.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/panel')
 */
class PanelController extends AbstractController
{
    use ModelUserController;
    use TypeBrowserController;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct()
    {
        $dbHelper = App::getDbHelper();
        $userRepository = $dbHelper->getRepository(User::class);
        $em = $dbHelper->get();
        $zapierHelper = App::getZapierHelper();
        $logger = LogHelper::getInstance();
        $this->userService = new UserService($userRepository, $em, $zapierHelper, $logger);
    }

    protected function getMode(): ?string
    {
        return 'panel';
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("", methods={"GET", "POST"}, name="panel.index")
     */
    public function indexAction(): ResponseInterface
    {
        $query = App::getDbHelper()->getRepository(Instance::class)->createQueryBuilder('c');
        $statServerByRegion = $query
            ->select('c.region, COUNT(c.id) as cnt')
            ->where('c.owner = ' . $this->user->getId())
            ->groupBy('c.region')
            ->getQuery()->getArrayResult();

        return $this->render([
            'serverByRegion' => $statServerByRegion,
            'user' => $this->user,
            'title' => 'Dashboard - Helio Panel',
            'dashboardActive' => 'active',
            'partialJs' => ['donutChart'],
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
            'modalTemplates' => ['addJob'],
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
            'modalTemplates' => ['addInstance'],
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
            'partialJs' => ['profile'],
        ]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/admin", methods={"GET"}, name="user.admin")
     */
    public function adminAction(): ResponseInterface
    {
        if (!$this->user->isAdmin()) {
            return $this->response->withRedirect('/panel', StatusCode::HTTP_FOUND);
        }

        return $this->render([
            'users' => $this->userService->findAll(),
            'user' => $this->user,
            'title' => 'Admin - Helio Panel',
            'module' => 'admin',
            'adminActive' => 'active',
            'partialJs' => ['admin'],
        ]);
    }

    /**
     * Note: This has to be here becuase in the "user" module, we don't have the jwt information since that section isn't protected.
     *
     * @return ResponseInterface
     *
     * @Route("/logout", methods={"GET"}, name="user.logout")
     *
     * @throws Exception
     */
    public function LogoutUserAction(): ResponseInterface
    {
        $this->user->setLoggedOut();
        App::getDbHelper()->persist($this->user);
        App::getDbHelper()->flush();

        return CookieUtility::deleteCookie($this->response->withRedirect('/loggedout', StatusCode::HTTP_FOUND), 'token');
    }
}
