<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc;

use ArrayObject;
use Laminas\Config\Config;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Http\Request;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Uri\UriFactory;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class DispatchListenerTest extends TestCase
{
    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var Application
     */
    protected $application;

    public function setUp()
    {
        $appConfig = array(
            'modules' => array(),
            'module_listener_options' => array(
                'config_cache_enabled' => false,
                'cache_dir'            => 'data/cache',
                'module_paths'         => array(),
            ),
        );
        $config = function ($s) {
            return new Config(array());
        };
        $sm = $this->serviceManager = new ServiceManager(
            new ServiceManagerConfig(array(
                'invokables' => array(
                    'DispatchListener' => 'Laminas\Mvc\DispatchListener',
                    'Request'          => 'Laminas\Http\PhpEnvironment\Request',
                    'Response'         => 'Laminas\Http\PhpEnvironment\Response',
                    'RouteListener'    => 'Laminas\Mvc\RouteListener',
                    'ViewManager'      => 'LaminasTest\Mvc\TestAsset\MockViewManager',
                    'SendResponseListener' => 'LaminasTest\Mvc\TestAsset\MockSendResponseListener'
                ),
                'factories' => array(
                    'ControllerLoader'        => 'Laminas\Mvc\Service\ControllerLoaderFactory',
                    'ControllerPluginManager' => 'Laminas\Mvc\Service\ControllerPluginManagerFactory',
                    'RoutePluginManager'      => 'Laminas\Mvc\Service\RoutePluginManagerFactory',
                    'Application'             => 'Laminas\Mvc\Service\ApplicationFactory',
                    'HttpRouter'              => 'Laminas\Mvc\Service\RouterFactory',
                    'Config'                  => $config,
                ),
                'aliases' => array(
                    'Router'                 => 'HttpRouter',
                    'Configuration'          => 'Config',
                ),
            ))
        );
        $sm->setService('ApplicationConfig', $appConfig);
        $sm->setFactory('ServiceListener', 'Laminas\Mvc\Service\ServiceListenerFactory');
        $sm->setAllowOverride(true);

        $this->application = $sm->get('Application');
    }

    public function setupPathController()
    {
        $request = $this->serviceManager->get('Request');
        $uri     = UriFactory::factory('http://example.local/path');
        $request->setUri($uri);

        $router = $this->serviceManager->get('HttpRouter');
        $route  = Router\Http\Literal::factory(array(
            'route'    => '/path',
            'defaults' => array(
                'controller' => 'path',
            ),
        ));
        $router->addRoute('path', $route);
        $this->application->bootstrap();
    }

    public function testControllerLoaderComposedOfAbstractFactory()
    {
        $this->setupPathController();

        $controllerLoader = $this->serviceManager->get('ControllerLoader');
        $controllerLoader->addAbstractFactory('LaminasTest\Mvc\Controller\TestAsset\ControllerLoaderAbstractFactory');

        $log = array();
        $this->application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use (&$log) {
            $log['error'] = $e->getError();
        });

        $this->application->run();

        $event = $this->application->getMvcEvent();
        $dispatchListener = $this->serviceManager->get('DispatchListener');
        $return = $dispatchListener->onDispatch($event);

        $this->assertEmpty($log);
        $this->assertInstanceOf('Laminas\Http\PhpEnvironment\Response', $return);
        $this->assertSame(200, $return->getStatusCode());
    }

    public function testUnlocatableControllerLoaderComposedOfAbstractFactory()
    {
        $this->setupPathController();

        $controllerLoader = $this->serviceManager->get('ControllerLoader');
        $controllerLoader->addAbstractFactory('LaminasTest\Mvc\Controller\TestAsset\UnlocatableControllerLoaderAbstractFactory');

        $log = array();
        $this->application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use (&$log) {
            $log['error'] = $e->getError();
        });

        $this->application->run();
        $event = $this->application->getMvcEvent();
        $dispatchListener = $this->serviceManager->get('DispatchListener');
        $return = $dispatchListener->onDispatch($event);

        $this->assertArrayHasKey('error', $log);
        $this->assertSame('error-controller-not-found', $log['error']);
    }
}
