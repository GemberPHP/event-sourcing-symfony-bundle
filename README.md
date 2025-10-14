# 🫚 Gember Event Sourcing Symfony Bundle
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.3-8892BF.svg?style=flat)](http://www.php.net)

Gember Event Sourcing Symfony Bundle for ([gember/event-sourcing](https://github.com/GemberPHP/event-sourcing)).

## Installation
Install the Symfony Bundle with composer:

```bash
composer require gember/event-sourcing-symfony-bundle 
```

This package requires Symfony `^7.1`.

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
        enabled: true
        psr6: 
          service: '@cache.app'

        # Or set a PSR-16 compatible cache layer of your choice
        # psr16: '@some.psr16.service'
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
```

You can override any of these defaults however you like.

## Required dependencies
Some of the required dependencies also need to be configured separately.

### Symfony Messenger (`symfony/messenger`)
At least one message bus should be configured, with the name `@event.bus`. 

When this bus is configured, _Gember Event Sourcing_ works out of the box.
However, when a different event bus is preferred, it must be a service implementing `Symfony\Component\Messenger\MessageBusInterface`.

### Symfony Cache (`symfony/cache`)
_Gember Event Sourcing_ makes use of `@cache.app`. 
This cache service is automatically configured when using Symfony framework including `symfony/cache`.

When this cache service is configured, _Gember Event Sourcing_ works out of the box.
However, when a different cache pool is preferred, it must be a service implementing `Psr\Cache\CacheItemPoolInterface` (PSR-6) or `Psr\SimpleCache\CacheInterface` (PSR-16).

### Symfony Serializer (`symfony/serializer`)
_Gember Event Sourcing_ makes use of `@serializer`.
This serializer service is automatically configured when using Symfony framework including `symfony/serializer`.

When this serializer service is configured, _Gember Event Sourcing_ works out of the box.
However, when a different serializer is preferred, it must be a service implementing `Symfony\Component\Serializer\SerializerInterface`.

### Doctrine DBAL/ORM (`doctrine/dbal`, `doctrine/orm`)
_Gember Event Sourcing_ makes use of `@doctrine.dbal.default_connection`.
This connection service is automatically configured when using Symfony framework including `doctrine/dbal` or `doctrine/orm`.

When this connection service is configured, _Gember Event Sourcing_ works out of the box.
However, when a different Doctrine connection is preferred, it must be a service implementing `Doctrine\DBAL\Connection`.

## Database
In order to persist all domain events in database, a running SQL database is needed.
The event store requires two tables. Schema is available in either raw SQL or in a migration file format:

Raw SQL schema: https://github.com/GemberPHP/rdbms-event-store-doctrine-dbal/blob/main/resources/schema.sql

Migrations:
- Doctrine migrations: https://github.com/GemberPHP/rdbms-event-store-doctrine-dbal/blob/main/resources/migrations/doctrine
- Phinx migrations: https://github.com/GemberPHP/rdbms-event-store-doctrine-dbal/tree/main/resources/migrations/phinx

## Good to go! 
Check the main package [gember/event-sourcing](https://github.com/GemberPHP/event-sourcing) or 
the demo application [gember/example-event-sourcing-dcb](https://github.com/GemberPHP/example-event-sourcing-dcb) for examples.