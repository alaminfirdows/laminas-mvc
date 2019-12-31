<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Mvc\Service;

use Interop\Container\ContainerInterface;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\SharedEventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\Listener\ServiceListener;
use Laminas\ModuleManager\ModuleManager;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\ServiceLocatorAwareInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\ServiceManager\ServiceManagerAwareInterface;
use Laminas\Stdlib\ArrayUtils;

class ServiceManagerConfig extends Config
{

    /**
     * Default service configuration.
     *
     * In addition to these, the constructor registers several factories and
     * initializers; see that method for details.
     *
     * @var array
     */
    protected $config = [
        'abstract_factories' => [],
        'aliases'            => [
            'EventManagerInterface'            => EventManager::class,
            EventManagerInterface::class       => 'EventManager',
            ModuleManager::class               => 'ModuleManager',
            ServiceListener::class             => 'ServiceListener',
            SharedEventManager::class          => 'SharedEventManager',
            'SharedEventManagerInterface'      => 'SharedEventManager',
            SharedEventManagerInterface::class => 'SharedEventManager',
        ],
        'delegators' => [],
        'factories'  => [
            'EventManager'            => EventManagerFactory::class,
            'ModuleManager'           => ModuleManagerFactory::class,
            'ServiceListener'         => ServiceListenerFactory::class,
        ],
        'lazy_services' => [],
        'initializers'  => [],
        'invokables'    => [],
        'services'      => [],
        'shared'        => [
            'EventManager' => false,
        ],
    ];

    /**
     * Constructor
     *
     * Merges internal arrays with those passed via configuration, and also
     * defines:
     *
     * - factory for the service 'SharedEventManager'.
     * - initializer for EventManagerAwareInterface implementations
     * - initializer for ServiceManagerAwareInterface implementations
     * - initializer for ServiceLocatorAwareInterface implementations
     *
     * @param  array $config
     */
    public function __construct(array $config = [])
    {
        $this->config['factories']['SharedEventManager'] = function () {
            return new SharedEventManager();
        };

        $this->config['initializers'] = ArrayUtils::merge($this->config['initializers'], [
            'EventManagerAwareInitializer' => function ($first, $second) {
                if ($first instanceof ContainerInterface) {
                    $container = $first;
                    $instance = $second;
                } else {
                    $container = $second;
                    $instance = $first;
                }

                if (! $instance instanceof EventManagerAwareInterface) {
                    return;
                }

                $eventManager = $instance->getEventManager();

                // If the instance has an EM WITH an SEM composed, do nothing.
                if ($eventManager instanceof EventManagerInterface
                    && $eventManager->getSharedManager() instanceof SharedEventManagerInterface
                ) {
                    return;
                }

                $instance->setEventManager($container->get('EventManager'));
            },
            'ServiceManagerAwareInitializer' => function ($first, $second) {
                if ($first instanceof ContainerInterface) {
                    $container = $first;
                    $instance = $second;
                } else {
                    $container = $second;
                    $instance = $first;
                }

                if ($container instanceof ServiceManager && $instance instanceof ServiceManagerAwareInterface) {
                    trigger_error(sprintf(
                        'ServiceManagerAwareInterface is deprecated and will be removed in version 3.0, along '
                        . 'with the ServiceManagerAwareInitializer. Please update your class %s to remove '
                        . 'the implementation, and start injecting your dependencies via factory instead.',
                        get_class($instance)
                    ), E_USER_DEPRECATED);
                    $instance->setServiceManager($container);
                }
            },
            'ServiceLocatorAwareInitializer' => function ($first, $second) {
                if ($first instanceof ContainerInterface) {
                    $container = $first;
                    $instance = $second;
                } else {
                    $container = $second;
                    $instance = $first;
                }

                // For service locator aware classes, inject the service
                // locator, but emit a deprecation notice. Skip plugin manager
                // implementations; they're dealt with later.
                if ($instance instanceof ServiceLocatorAwareInterface
                    && ! $instance instanceof AbstractPluginManager
                ) {
                    trigger_error(sprintf(
                        'ServiceLocatorAwareInterface is deprecated and will be removed in version 3.0, along '
                        . 'with the ServiceLocatorAwareInitializer. Please update your class %s to remove '
                        . 'the implementation, and start injecting your dependencies via factory instead.',
                        get_class($instance)
                    ), E_USER_DEPRECATED);
                    $instance->setServiceLocator($container);
                }

                // For service locator aware plugin managers that do not have
                // the service locator already injected, inject it, but emit a
                // deprecation notice.
                if ($instance instanceof ServiceLocatorAwareInterface
                    && $instance instanceof AbstractPluginManager
                    && ! $instance->getServiceLocator()
                ) {
                    trigger_error(sprintf(
                        'ServiceLocatorAwareInterface is deprecated and will be removed in version 3.0, along '
                        . 'with the ServiceLocatorAwareInitializer. Please update your %s plugin manager factory '
                        . 'to inject the parent service locator via the constructor.',
                        get_class($instance)
                    ), E_USER_DEPRECATED);
                    $instance->setServiceLocator($container);
                }
            },
        ]);

        // In laminas-servicemanager v2, incoming configuration is not merged
        // with existing; it replaces. So we need to detect that and merge.
        if (method_exists($this, 'getAllowOverride')) {
            $config = ArrayUtils::merge($this->config, $config);
        }

        parent::__construct($config);
    }

    /**
     * Configure service container.
     *
     * Uses the configuration present in the instance to configure the provided
     * service container.
     *
     * Before doing so, it adds a "service" entry for the ServiceManager class,
     * pointing to the provided service container.
     *
     * @param ServiceManager $services
     * @return ServiceManager
     */
    public function configureServiceManager(ServiceManager $services)
    {
        $this->config['services'][ServiceManager::class] = $services;

        /*
        printf("Configuration prior to configuring servicemanager:\n");
        foreach ($this->config as $type => $list) {
            switch ($type) {
                case 'aliases':
                case 'delegators':
                case 'factories':
                case 'invokables':
                case 'lazy_services':
                case 'services':
                case 'shared':
                    foreach (array_keys($list) as $name) {
                        printf("    %s (%s)\n", $name, $type);
                    }
                    break;

                case 'initializers':
                case 'abstract_factories':
                    foreach ($list as $callable) {
                        printf("    %s (%s)\n", (is_object($callable) ? get_class($callable) : $callable), $type);
                    }
                    break;

                default:
                    break;
            }
        }
         */

        // This is invoked as part of the bootstrapping process, and requires
        // the ability to override services.
        $services->setAllowOverride(true);
        parent::configureServiceManager($services);
        $services->setAllowOverride(false);

        return $services;
    }

    /**
     * Return all service configuration (v3)
     *
     * @return array
     */
    public function toArray()
    {
        return $this->config;
    }
}
