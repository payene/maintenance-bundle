<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\DependencyInjection;

use Payene\MaintenanceBundle\Storage\CacheStorage;
use Payene\MaintenanceBundle\Storage\JsonFileStorage;
use Payene\MaintenanceBundle\Storage\MaintenanceStorageInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class MaintenanceExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('maintenance.panel_path', $config['panel_path']);

        $this->registerStorage($config, $container);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
    }

    private function registerStorage(array $config, ContainerBuilder $container): void
    {
        if ($config['storage'] === 'cache') {
            if (!interface_exists(\Psr\Cache\CacheItemPoolInterface::class)) {
                throw new \LogicException(
                    'Le storage "cache" nécessite symfony/cache. Exécutez : composer require symfony/cache'
                );
            }

            $container->register(CacheStorage::class)
                ->setAutowired(false)
                ->setArguments([new Reference($config['cache_pool'])]);

            $container->setAlias(MaintenanceStorageInterface::class, CacheStorage::class);

            return;
        }

        $container->register(JsonFileStorage::class)
            ->setAutowired(false)
            ->setArguments([$config['json_path']]);

        $container->setAlias(MaintenanceStorageInterface::class, JsonFileStorage::class);
    }
}