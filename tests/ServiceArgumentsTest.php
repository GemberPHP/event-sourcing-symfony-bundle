<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle\Test;

use Gember\EventSourcingSymfonyBundle\GemberEventSourcingBundle;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Compiles the container with every feature enabled and asserts each Gember service gets as many arguments as its
 * constructor takes: a constructor change in a Gember package (an argument added or removed) fails here instead
 * of at runtime, or silently.
 *
 * @internal
 */
final class ServiceArgumentsTest extends TestCase
{
    /**
     * Services provided by the application (DoctrineBundle, FrameworkBundle, MonologBundle, ...).
     */
    private const array EXTERNAL_SERVICES = [
        'doctrine.dbal.default_connection',
        'logger',
        'command.bus',
        'event.bus',
        'messenger.default_bus',
        'serializer',
        'serializer.normalizer.object',
        'cache.app',
        'Symfony\Component\Uid\Factory\UuidFactory',
        'Symfony\Component\Uid\Factory\UlidFactory',
    ];

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('provideConfigurations')]
    public function itShouldPassAsManyArgumentsAsEachConstructorTakes(array $config): void
    {
        $builder = $this->compile($config);

        $checked = 0;

        foreach ($builder->getDefinitions() as $id => $definition) {
            $className = $definition->getClass();

            if ($className === null || !str_starts_with($className, 'Gember\\') || $definition->getFactory() !== null) {
                continue;
            }

            self::assertTrue(class_exists($className), sprintf('Service %s: class %s does not exist', $id, $className));

            $constructor = (new ReflectionClass($className))->getConstructor();
            $arguments = count($definition->getArguments());

            self::assertGreaterThanOrEqual(
                $constructor?->getNumberOfRequiredParameters() ?? 0,
                $arguments,
                sprintf('Service %s (%s) gets too few constructor arguments', $id, $className),
            );
            self::assertLessThanOrEqual(
                $constructor?->getNumberOfParameters() ?? 0,
                $arguments,
                sprintf('Service %s (%s) gets more constructor arguments than it takes', $id, $className),
            );

            ++$checked;
        }

        self::assertGreaterThan(50, $checked);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideConfigurations(): iterable
    {
        yield 'direct, cache (psr6) and snapshots' => [[
            'cache' => ['enabled' => true],
            'snapshot' => ['enabled' => true],
            'dispatch' => ['strategy' => 'direct'],
        ]];

        yield 'outbox, cache (psr16) and snapshots' => [[
            'cache' => ['enabled' => true, 'psr16' => ['service' => '@cache.app']],
            'snapshot' => ['enabled' => true],
            'dispatch' => ['strategy' => 'outbox'],
        ]];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.project_dir', sys_get_temp_dir());
        $builder->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.debug', false);

        $extension = (new GemberEventSourcingBundle())->getContainerExtension();
        self::assertNotNull($extension);

        $builder->registerExtension($extension);
        $builder->loadFromExtension($extension->getAlias(), $config);

        foreach (self::EXTERNAL_SERVICES as $id) {
            $builder->setDefinition($id, (new Definition(stdClass::class))->setSynthetic(true)->setPublic(true));
        }

        // Keep every Gember service after compiling, so its arguments can be inspected
        $builder->addCompilerPass(new class implements CompilerPassInterface {
            #[Override]
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $definition) {
                    if (str_starts_with((string) $definition->getClass(), 'Gember\\')) {
                        $definition->setPublic(true);
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);

        $builder->compile();

        return $builder;
    }
}
