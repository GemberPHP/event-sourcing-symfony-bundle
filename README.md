# 🫚 Gember Event Sourcing Symfony Bundle
[![Build Status](https://github.com/GemberPHP/event-sourcing-symfony-bundle/actions/workflows/test.yaml/badge.svg?branch=main)](https://github.com/GemberPHP/event-sourcing-symfony-bundle/actions/workflows/test.yaml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.3-8892BF.svg?style=flat)](http://www.php.net)

Gember Event Sourcing Symfony Bundle for ([gember/event-sourcing](https://github.com/GemberPHP/event-sourcing)).

## Installation
Install the Symfony Bundle with composer:

```bash
composer require gember/event-sourcing-symfony-bundle 
```

Symfony Flex enables the bundle automatically. Without Flex, add it to `config/bundles.php`:

```php
return [
    // ...
    Gember\EventSourcingSymfonyBundle\GemberEventSourcingBundle::class => ['all' => true],
];
```

This package requires Symfony `^7.1` or `^8.0`, and the PHP extension `pcntl` (for graceful shutdown of the outbox processor).

## Configuration
This package installs _Gember Event Sourcing_ with all required dependency adapters.
Some of these adapters need to be configured.

By default, it uses the following configuration (`gember_event_sourcing.yaml`):
```yaml
gember_event_sourcing:
    message_bus:
        symfony:
            event_bus: '@event.bus'
            command_bus: '@command.bus'
    cache:
        enabled: false
        psr6:
            service: '@cache.app'

        # Or set a PSR-16 compatible cache layer of your choice
        # psr16:
        #     service: '@some.psr16.service'
    serializer:
        symfony:
            serializer: '@serializer'
    event_store:
        rdbms:
            doctrine_dbal:
                connection: '@doctrine.dbal.default_connection'
    generator:
        identity:
            # Use Gember alias of @Symfony\Component\Uid\Factory\UuidFactory:
            service: '@gember.identity_generator_symfony.uuid.symfony_uuid_identity_generator'

            # Or use Gember alias of @Symfony\Component\Uid\Factory\UlidFactory:
            # service: '@gember.identity_generator_symfony.ulid.symfony_ulid_identity_generator'
    registry:
        event:
            reflector:
                path: '%kernel.project_dir%/src'
        command_handler:
            reflector:
                path: '%kernel.project_dir%/src'
        saga:
            reflector:
                path: '%kernel.project_dir%/src'
    logging:
        logger: '@logger'
    snapshot:
        enabled: false      # see the Snapshotting docs
    saga:
        # command_bus: '@some.command_bus'   # commands recorded by sagas; defaults to message_bus.symfony.command_bus, see below
    dispatch:
        strategy: direct    # 'direct' or 'outbox', see the Outbox docs
        max_retries: 5      # outbox only
```

You can override any of these defaults however you like. See the [documentation](https://gemberphp.github.io/event-sourcing/) for [caching](https://gemberphp.github.io/event-sourcing/usage/caching/), [snapshotting](https://gemberphp.github.io/event-sourcing/usage/snapshotting/) and the [outbox](https://gemberphp.github.io/event-sourcing/usage/outbox/).

Use cases and sagas must be registered as autoconfigured services (the Symfony default for `src/`): their `#[DomainCommandHandler]` and `#[SagaEventSubscriber]` methods are routed to the message buses through autoconfiguration.

## Required dependencies
Some of the required dependencies also need to be configured separately.

### Symfony Messenger (`symfony/messenger`)
Two message buses are used: `command.bus` and `event.bus`. Events without handlers are normal, so allow them on the event bus:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus:
            event.bus:
                default_middleware:
                    allow_no_handlers: true
```

Other buses can be configured with `message_bus.symfony.event_bus` and `command_bus`; they must be services implementing `Symfony\Component\Messenger\MessageBusInterface`.

#### Saga command bus
Commands recorded by sagas are dispatched on `message_bus.symfony.command_bus` by default. To dispatch them differently (for example asynchronously) without affecting the commands your application dispatches, set `saga.command_bus` to a service implementing `Gember\DependencyContracts\Util\Messaging\MessageBus\CommandBus`. With the outbox strategy, sagas still record their commands in the outbox, and the outbox processor delivers them through this service.

With an asynchronous saga command bus, use the outbox strategy for at-least-once delivery, and make the use cases handling these commands idempotent. With the direct strategy, a command can be lost when dispatching it fails after the saga is saved.

### Symfony Cache (`symfony/cache`)
When caching is enabled, _Gember Event Sourcing_ makes use of `@cache.app` by default.
This cache service is automatically configured when using Symfony framework including `symfony/cache`.
A different cache pool must be a service implementing `Psr\Cache\CacheItemPoolInterface` (PSR-6) or `Psr\SimpleCache\CacheInterface` (PSR-16).

### Symfony Serializer (`symfony/serializer`)
_Gember Event Sourcing_ makes use of `@serializer`.
This serializer service is automatically configured when using Symfony framework including `symfony/serializer`.
A different serializer must be a service implementing `Symfony\Component\Serializer\SerializerInterface`.

### Doctrine DBAL (`doctrine/doctrine-bundle`)
_Gember Event Sourcing_ makes use of `@doctrine.dbal.default_connection`, provided by `doctrine/doctrine-bundle`.
A different connection must be a service implementing `Doctrine\DBAL\Connection`.

## Database
Events, sagas, snapshots and outbox messages are stored in MySQL, MariaDB (InnoDB) or SQLite. Create the tables with the migrations or schema of [gember/rdbms-event-store-doctrine-dbal](https://github.com/GemberPHP/rdbms-event-store-doctrine-dbal#database), and see [Upgrading](https://gemberphp.github.io/event-sourcing/upgrading/) when upgrading.

## Good to go!
Check the main package [gember/event-sourcing](https://github.com/GemberPHP/event-sourcing) or
the demo application [gember/example-event-sourcing-dcb](https://github.com/GemberPHP/example-event-sourcing-dcb) for examples.
