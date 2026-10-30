<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('maintenance');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->enumNode('storage')
                    ->info('Type de stockage : "json" (défaut) ou "cache" (nécessite symfony/cache).')
                    ->values(['json', 'cache'])
                    ->defaultValue('json')
                ->end()
                ->scalarNode('json_path')
                    ->info('Chemin du fichier JSON (storage "json" uniquement).')
                    ->defaultValue('%kernel.project_dir%/var/maintenance.json')
                ->end()
                ->scalarNode('cache_pool')
                    ->info('Service ID du cache pool à utiliser (storage "cache" uniquement).')
                    ->defaultValue('cache.app')
                ->end()
                ->scalarNode('panel_path')
                    ->info('Préfixe des routes de l\'interface d\'administration.')
                    ->defaultValue('/admin/maintenance')
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}