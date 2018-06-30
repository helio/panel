<?php

namespace Helio\Panel\Controller;

use Ergy\Slim\Annotations\Controller;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Model\User;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\Http\EnvironmentInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RouterInterface;
use Slim\Views\PhpRenderer;

/**
 * Abstract Panel Controller
 *
 * @property PhpRenderer renderer
 * @property Logger logger
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
     * @var string
     */
    protected $zapierHookUrl;


    /**
     * AbstractPanelController constructor.
     *
     * @throws \RuntimeException
     */
    public function __construct()
    {
        if (!array_key_exists('ZAPIER_HOOK_URL', $_SERVER)) {
            throw new \RuntimeException('Zapier hook URL not set');
        }
        $this->zapierHookUrl = $_SERVER['ZAPIER_HOOK_URL'];
    }


    /**
     * @param User $user
     *
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function submitUserToZapier(User $user): bool
    {

        // TODO: submit proper email to zapier
        $publicUserObject = json_encode([
            'name' => $user->getName(),
            'email' => 'we don\'t want zapier adding users all the time' //$user->getEmail()
        ]);

        $result = ZapierHelper::get()->exec('POST', $this->zapierHookUrl, ['body' => $publicUserObject]);

        return ($result->getStatusCode() === 200);
    }


    /**
     * @param string $template
     * @param array $params
     *
     * @return ResponseInterface
     */
    protected function render(string $template, array $params = []): ResponseInterface
    {
        if (!$template) {
            throw new \InvalidArgumentException('No template specified', 1530051401);
        }
        $params = array_merge_recursive($params, ['childTemplate' => $template], $this->request->getParams());

        return $this->renderer->render($this->response,
            'index.phtml',
            $params
        );
    }
}