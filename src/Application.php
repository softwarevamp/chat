<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Elfet\Chat;

use Silex\Application\UrlGeneratorTrait;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class Application extends \Silex\Application
{
    use UrlGeneratorTrait;

    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $app = $this;

        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new SessionServiceProvider());

        $app['facebook'] = $app->share(function () use ($app) {
            return new \Facebook([
                'appId' => $app['facebook.app_id'],
                'secret' => $app['facebook.secret'],
                'allowSignedRequest' => false
            ]);
        });

        $app['user'] = function () use ($app) {
            return $app['session']->get('user');
        };

        $app->before(function ($request) use ($app) {
            $user = $app['user'];

            if (null === $user) {
                $facebook = $app['facebook'];
                $result = $facebook->api(array(
                    'method' => 'fql.query',
                    'query' => 'SELECT uid, name, pic_square FROM user WHERE uid = me()',
                ));

                if (!empty($result)) {
                    $app['session']->set('user', $result[0]);
                    return;
                }

                return $app->render('login.phtml', [
                    'loginUrl' => $facebook->getLoginUrl(),
                ]);
            }
        });
    }

    public function render($viewPath, $params = [])
    {
        $viewPath = $this['view_dir'] . $viewPath;

        $basePath = $this['request']->getBasePath();

        extract($params);
        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        return new Response($content);
    }
} 