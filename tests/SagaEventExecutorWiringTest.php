<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle\Test;

use Gember\EventSourcing\Saga\Default\DefaultSagaEventExecutor;
use Gember\EventSourcing\Saga\Loggable\LoggableSagaEventExecutorDecorator;
use Gember\EventSourcing\Saga\Retrying\RetryingSagaEventExecutorDecorator;
use Gember\EventSourcing\Saga\Transactional\TransactionalSagaEventExecutorDecorator;
use Gember\EventSourcingSymfonyBundle\GemberEventSourcingBundle;
use Gember\RdbmsEventStoreDoctrineDbal\Transaction\DoctrineDbalTransactional;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiles the container and asserts the saga event executor's decorators: the retrying decorator must be the
 * outermost one (so each attempt runs in its own transaction) and needs the transaction manager to detect a
 * transaction it didn't open.
 *
 * @internal
 */
final class SagaEventExecutorWiringTest extends TestCase
{
    public const string SAGA_EVENT_EXECUTOR = 'gember.event_sourcing.saga.saga_event_executor';

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

    private ContainerBuilder $builder;

    #[Test]
    public function itShouldRetryOutsideLoggingInDirectMode(): void
    {
        $chain = $this->compileDecoratorChain('direct');

        self::assertSame([
            RetryingSagaEventExecutorDecorator::class,
            LoggableSagaEventExecutorDecorator::class,
            DefaultSagaEventExecutor::class,
        ], array_map(fn(Definition $definition) => $definition->getClass(), $chain));

        $this->assertRetryingDecoratorArguments($chain[0]);
    }

    #[Test]
    public function itShouldRetryOutsideTheTransactionInOutboxMode(): void
    {
        $chain = $this->compileDecoratorChain('outbox');

        self::assertSame([
            RetryingSagaEventExecutorDecorator::class,
            TransactionalSagaEventExecutorDecorator::class,
            LoggableSagaEventExecutorDecorator::class,
            DefaultSagaEventExecutor::class,
        ], array_map(fn(Definition $definition) => $definition->getClass(), $chain));

        $this->assertRetryingDecoratorArguments($chain[0]);

        // Both decorators must check and open transactions on the same connection
        $transactional = $this->resolve($chain[1]->getArgument(1));
        self::assertSame(DoctrineDbalTransactional::class, $transactional->getClass());
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $transactional->getArgument(0));
    }

    private function assertRetryingDecoratorArguments(Definition $retrying): void
    {
        $transactional = $this->resolve($retrying->getArgument(1));
        self::assertSame(DoctrineDbalTransactional::class, $transactional->getClass());
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $transactional->getArgument(0));

        self::assertEquals(new Reference('logger'), $retrying->getArgument(2));
    }

    /**
     * @return list<Definition> Outermost decorator first
     */
    private function compileDecoratorChain(string $dispatchStrategy): array
    {
        $this->builder = new ContainerBuilder();
        $this->builder->setParameter('kernel.project_dir', sys_get_temp_dir());
        $this->builder->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->builder->setParameter('kernel.environment', 'test');
        $this->builder->setParameter('kernel.debug', false);

        $extension = new GemberEventSourcingBundle()->getContainerExtension();
        self::assertNotNull($extension);

        $this->builder->registerExtension($extension);
        $this->builder->loadFromExtension($extension->getAlias(), ['dispatch' => ['strategy' => $dispatchStrategy]]);

        foreach (self::EXTERNAL_SERVICES as $id) {
            $this->builder->setDefinition($id, new Definition(stdClass::class)->setSynthetic(true)->setPublic(true));
        }

        // Keep the (private) executor after compiling, so its decorators can be inspected. Once decorated, its id is
        // an alias of the outermost decorator.
        $this->builder->addCompilerPass(new class implements CompilerPassInterface {
            #[Override]
            public function process(ContainerBuilder $container): void
            {
                $id = SagaEventExecutorWiringTest::SAGA_EVENT_EXECUTOR;

                if ($container->hasAlias($id)) {
                    $container->getAlias($id)->setPublic(true);

                    return;
                }

                $container->getDefinition($id)->setPublic(true);
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);

        $this->builder->compile();

        $chain = [];
        $definition = $this->builder->findDefinition(self::SAGA_EVENT_EXECUTOR);

        while (true) {
            $chain[] = $definition;

            if ($definition->getClass() === DefaultSagaEventExecutor::class) {
                return $chain;
            }

            $definition = $this->resolve($definition->getArgument(0));
        }
    }

    private function resolve(mixed $argument): Definition
    {
        if ($argument instanceof Reference) {
            return $this->builder->findDefinition((string) $argument);
        }

        self::assertInstanceOf(Definition::class, $argument);

        return $argument;
    }
}
