<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle;

use Gember\EventSourcing\Saga\Attribute\SagaEventSubscriber;
use Gember\EventSourcing\Saga\SagaEventHandler;
use Gember\EventSourcing\Snapshot\Policy\SnapshotPolicy;
use Gember\EventSourcing\UseCase\Attribute\DomainCommandHandler;
use Gember\EventSourcing\UseCase\CommandHandler\UseCaseCommandHandler;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Override;
use ReflectionMethod;

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
                ->arrayNode('registry')
                    ->children()
                        ->arrayNode('event')
                            ->children()
                                ->arrayNode('reflector')
                                    ->children()
                                        ->scalarNode('path')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('command_handler')
                            ->children()
                                ->arrayNode('reflector')
                                    ->children()
                                        ->scalarNode('path')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('saga')
                            ->children()
                                ->arrayNode('reflector')
                                    ->children()
                                        ->scalarNode('path')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('message_bus')
                    ->children()
                        ->arrayNode('symfony')
                            ->children()
                                ->scalarNode('event_bus')->end()
                                ->scalarNode('command_bus')->end()
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
                ->arrayNode('logging')
                    ->children()
                        ->scalarNode('logger')->end()
                    ->end()
                ->end()
                ->arrayNode('snapshot')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('dispatch')
                    ->children()
                        ->enumNode('strategy')
                            ->values(['direct', 'outbox'])
                            ->defaultValue('direct')
                        ->end()
                        ->integerNode('max_retries')
                            ->defaultValue(5)
                            ->min(1)
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
            $services->get('gember.event_sourcing.registry.event.cached.cached_event_registry_decorator')
                ->decorate('gember.event_sourcing.registry.event.event_registry');

            $services->get('gember.event_sourcing.registry.command_handler.cached.cached_command_handler_registry_decorator')
                ->decorate('gember.event_sourcing.registry.command_handler.command_handler_registry');

            $services->get('gember.event_sourcing.registry.saga.cached.cached_saga_registry_decorator')
                ->decorate('gember.event_sourcing.registry.saga.saga_registry');

            $services->get('gember.event_sourcing.resolver.domain_event.cached.cached_domain_event_resolver_decorator')
                ->decorate('gember.event_sourcing.resolver.domain_event.domain_event_resolver');

            $services->get('gember.event_sourcing.resolver.domain_command.cached.cached_domain_command_resolver_decorator')
                ->decorate('gember.event_sourcing.resolver.domain_command.domain_command_resolver');

            $services->get('gember.event_sourcing.resolver.use_case.cached.cached_use_case_resolver_decorator')
                ->decorate('gember.event_sourcing.resolver.use_case.use_case_resolver');

            $services->get('gember.event_sourcing.resolver.saga.cached.cached_saga_resolver_decorator')
                ->decorate('gember.event_sourcing.resolver.saga.saga_resolver');

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

        $services->get('gember.event_sourcing.registry.event.event_registry')
            ->arg('$path', $config['registry']['event']['reflector']['path'] ?? '%kernel.project_dir%/src');

        $services->get('gember.event_sourcing.registry.command_handler.command_handler_registry')
            ->arg('$path', $config['registry']['command_handler']['reflector']['path'] ?? '%kernel.project_dir%/src');

        $services->get('gember.event_sourcing.registry.saga.saga_registry')
            ->arg('$path', $config['registry']['saga']['reflector']['path'] ?? '%kernel.project_dir%/src');

        if (!empty($config['message_bus']['symfony']['event_bus'] ?? null)) {
            $services->alias(
                'gember.symfony.component.messenger.message_bus.event_bus',
                ltrim($config['message_bus']['symfony']['event_bus'], '@'),
            );
        }

        if (!empty($config['message_bus']['symfony']['command_bus'] ?? null)) {
            $services->alias(
                'gember.symfony.component.messenger.message_bus.command_bus',
                ltrim($config['message_bus']['symfony']['command_bus'], '@'),
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

        if (!empty($config['logging']['logger'] ?? null)) {
            $services->alias(
                'gember.psr.log.logger_interface',
                ltrim($config['logging']['logger'], '@'),
            );
        }

        // Snapshot decorator must be activated BEFORE the transactional decorator (outbox).
        // Symfony's later decorates() calls wrap earlier ones, resulting in:
        // Transactional(Snapshot(EventSourced)) — snapshot save happens inside the transaction.
        if ($config['snapshot']['enabled'] ?? false) {
            $services->get('gember.event_sourcing.repository.snapshot.snapshot_use_case_repository_decorator')
                ->decorate('gember.event_sourcing.repository.use_case_repository');
        } else {
            $services->remove('gember.event_store.snapshot.rdbms_snapshot_store_repository');
            $services->remove('gember.event_sourcing.snapshot.snapshot_store');
            $services->remove('gember.rdbms_event_store_doctrine_dbal.snapshot.table_schema.snapshot_store_table_schema');
            $services->remove('gember.event_sourcing.snapshot.policy.after_events');
            $services->remove('gember.event_sourcing.snapshot.policy.after_sourcing_time');
            $services->remove('gember.event_sourcing.snapshot.policy.on_events');
            $services->remove('gember.event_sourcing.snapshot.loggable.loggable_snapshot_store_decorator');
            $services->remove('gember.event_sourcing.repository.snapshot.snapshot_use_case_repository_decorator');
        }

        if (($config['dispatch']['strategy'] ?? 'direct') === 'outbox') {
            $container->import(__DIR__ . '/../config/outbox_services.yaml');

            // Replace event bus with outbox variant
            $services->get('gember.event_sourcing.util.messaging.message_bus.event_bus')
                ->class(\Gember\EventSourcing\Outbox\Bus\OutboxEventBus::class)
                ->args([service('gember.event_sourcing.outbox.outbox_store')]);

            // Replace command bus with outbox variant
            $services->get('gember.event_sourcing.util.messaging.message_bus.command_bus')
                ->class(\Gember\EventSourcing\Outbox\Bus\OutboxCommandBus::class)
                ->args([service('gember.event_sourcing.outbox.outbox_store')]);

            // Activate transactional decorators
            $services->get('gember.event_sourcing.outbox.transactional_use_case_repository')
                ->decorate('gember.event_sourcing.repository.use_case_repository');

            $services->get('gember.event_sourcing.outbox.transactional_saga_event_executor')
                ->decorate('gember.event_sourcing.saga.saga_event_executor');

            // Configure max retries
            $services->get(\Gember\EventSourcing\Outbox\Processor\OutboxProcessor::class)
                ->arg('$maxRetries', $config['dispatch']['max_retries'] ?? 5);
        }

        $builder->registerAttributeForAutoconfiguration(
            DomainCommandHandler::class,
            function (ChildDefinition $definition, DomainCommandHandler $attribute, ReflectionMethod $reflector) use ($builder, $config): void {
                $parameter = $reflector->getParameters()[0];

                $bus = $config['message_bus']['symfony']['command_bus'] ?? 'command.bus';

                $builder
                    ->getDefinition(UseCaseCommandHandler::class)
                    ->addTag('messenger.message_handler', [
                        'bus' => str_starts_with($bus, '@') ? substr($bus, 1) : $bus,
                        'handles' => $parameter->getType()->getName(),
                        'method' => '__invoke',
                    ]);
            },
        );

        $builder->registerAttributeForAutoconfiguration(
            SagaEventSubscriber::class,
            function (ChildDefinition $definition, SagaEventSubscriber $attribute, ReflectionMethod $reflector) use ($builder, $config): void {
                $parameter = $reflector->getParameters()[0];

                $bus = $config['message_bus']['symfony']['event_bus'] ?? 'event.bus';

                $builder
                    ->getDefinition(SagaEventHandler::class)
                    ->addTag('messenger.message_handler', [
                        'bus' => str_starts_with($bus, '@') ? substr($bus, 1) : $bus,
                        'handles' => $parameter->getType()->getName(),
                        'method' => '__invoke',
                    ]);
            },
        );

        $builder->registerForAutoconfiguration(SnapshotPolicy::class)
            ->addTag('gember.snapshot.policy');
    }
}
