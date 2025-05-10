<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Override;

final class GemberEventSourcingBundle extends AbstractBundle
{
    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('cache')
                    ->children()
                        ->booleanNode('enabled')->end()
                        ->arrayNode('psr6')
                            ->children()
                                ->scalarNode('service')->end()
                            ->end()
                        ->end()
                        ->arrayNode('psr16')
                            ->children()
                                ->scalarNode('service')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('event_registry')
                    ->children()
                        ->arrayNode('reflector')
                            ->children()
                                ->scalarNode('path')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('message_bus')
                    ->children()
                        ->arrayNode('symfony')
                            ->children()
                                ->scalarNode('event_bus')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('event_store')
                    ->children()
                        ->arrayNode('rdbms')
                            ->children()
                                ->arrayNode('doctrine_dbal')
                                    ->children()
                                        ->scalarNode('connection')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('generator')
                    ->children()
                        ->arrayNode('identity')
                            ->children()
                                ->scalarNode('service')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('serializer')
                    ->children()
                        ->arrayNode('symfony')
                            ->children()
                                ->scalarNode('serializer')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/../config/services.yaml');
        $services = $container->services();

        if ($config['cache']['enabled'] ?? false) {
            $services->get('gember.event_sourcing.registry.cached.cached_event_registry_decorator')
                ->decorate('gember.event_sourcing.registry.event_registry');

            $services->get('gember.event_sourcing.util.resolver.cached.cached_attribute_resolver_decorator')
                ->decorate('gember.event_sourcing.util.attribute.resolver.attribute_resolver');

            $cacheType = isset($config['cache']['psr16']) ? 'psr16' : 'psr6';
            $cacheService = ltrim($config['cache'][$cacheType]['service'] ?? 'cache.app', '@');

            switch ($cacheType) {
                case 'psr6':
                    $services->alias('gember.psr.cache.cache_item_pool_interface', $cacheService);
                    break;
                default:
                    $services->alias('gember.psr.simple_cache.cache_interface', $cacheService);
                    $services->remove('gember.psr.cache.cache_item_pool_interface');
                    break;
            }
        } else {
            // Remove all cache related service definitions
            $services->remove('gember.symfony.component.cache.psr16_cache');
            $services->remove('gember.psr.cache.cache_item_pool_interface');
        }

        $services->get('gember.event_sourcing.registry.event_registry')
            ->arg('$path', $config['registry']['event_registry']['reflector']['path'] ?? '%kernel.project_dir%/src');

        if (!empty($config['message_bus']['symfony']['event_bus'] ?? null)) {
            $services->alias(
                'gember.symfony.component.messenger.message_bus.event_bus',
                ltrim($config['message_bus']['symfony']['event_bus'], '@'),
            );
        }

        if (!empty($config['event_store']['rdbms']['doctrine_dbal']['connection'] ?? null)) {
            $services->alias(
                'gember.doctrine.dbal.connection',
                ltrim($config['event_store']['rdbms']['doctrine_dbal']['connection'], '@'),
            );
        }

        if (!empty($config['generator']['identity']['service'] ?? null)) {
            $services->alias(
                'gember.event_sourcing.util.generator.identity.identity_generator',
                ltrim($config['generator']['identity']['service'], '@'),
            );
        }

        if (!empty($config['serializer']['symfony']['serializer'] ?? null)) {
            $services->alias(
                'gember.symfony.component.serializer.serializer_interface',
                ltrim($config['serializer']['symfony']['serializer'], '@'),
            );
        }
    }
}
