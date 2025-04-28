# 🫚 Gember Event Sourcing Symfony Bundle
[![Build Status](https://scrutinizer-ci.com/g/GemberPHP/event-sourcing-symfony-bundle/badges/build.png?b=main)](https://github.com/GemberPHP/event-sourcing-symfony-bundle/actions)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/GemberPHP/event-sourcing-symfony-bundle.svg?style=flat)](https://scrutinizer-ci.com/g/GemberPHP/event-sourcing-symfony-bundle/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/GemberPHP/event-sourcing-symfony-bundle.svg?style=flat)](https://scrutinizer-ci.com/g/GemberPHP/event-sourcing-symfony-bundle)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.3-8892BF.svg?style=flat)](http://www.php.net)

Gember Event Sourcing Symfony Bundle for ([gember/event-sourcing](https://github.com/GemberPHP/event-sourcing)).

## Prerequisites
This package requires Symfony `^7.1`.

Several additional packages should be installed and configured in Symfony as well:

#### Symfony Messenger (`symfony/messenger`)
At least one message bus should be configured, with the name `@event.bus`. 

When this bus is configured, Gember Event Sourcing works out of the box.
However, when a different event bus is preferred, it can be overwritten by configuration (`gember_event_sourcing.yaml`).
```yaml
gember_event_sourcing:
    message_bus:
        symfony:
            event_bus: '@event.bus'
```
Note: It must be a service implementing `Symfony\Component\Messenger\MessageBusInterface`.

#### Symfony Cache (`symfony/cache`)
Gember Event Sourcing makes use of `@cache.app`. 
This cache service is automatically configured when using Symfony framework including `symfony/cache`.

When this cache service is configured, Gember Event Sourcing works out of the box.
However, when a different cache pool is preferred, it can be overwritten by configuration (`gember_event_sourcing.yaml`):
```yaml
gember_event_sourcing:
    cache:
        enabled: true
        psr6: '@cache.app'
        
        # Or set a PSR-16 compatible cache layer of your choice
        # psr16: '@some.psr16.service'
```
Note: It must be a service implementing `Psr\Cache\CacheItemPoolInterface` (PSR-6) or `Psr\SimpleCache\CacheInterface` (PSR-16).

#### Symfony Serializer (`symfony/serializer`)
Gember Event Sourcing makes use of `@serializer`.
This serializer service is automatically configured when using Symfony framework including `symfony/serializer`.

When this serializer service is configured, Gember Event Sourcing works out of the box.
However, when a different serializer is preferred, it can be overwritten by configuration (`gember_event_sourcing.yaml`):
```yaml
gember_event_sourcing:
    serializer:
        symfony:
          serializer: '@serializer'
```
Note: It must be a service implementing `Symfony\Component\Serializer\SerializerInterface`.

#### Doctrine DBAL/ORM (`doctrine/dbal`, `doctrine/orm`)
Gember Event Sourcing makes use of `@doctrine.dbal.default_connection`.
This connection service is automatically configured when using Symfony framework including `doctrine/dbal` or `doctrine/orm`.

When this connection service is configured, Gember Event Sourcing works out of the box.
However, when a different Doctrine connection is preferred, it can be overwritten by configuration (`gember_event_sourcing.yaml`):
```yaml
gember_event_sourcing:
  event_store:
    rdbms:
      doctrine_dbal:
        connection: '@doctrine.dbal.default_connection'
```
Note: It must be a service implementing `Doctrine\DBAL\Connection`.

#### Symfony UID (`symfony/uid`)
Gember Event Sourcing makes use of `@Symfony\Component\Uid\Factory\UuidFactory` or `Symfony\Component\Uid\Factory\UlidFactory`.

These factories are automatically configured when using Symfony framework including `symfony/uid`.

By default, the UUID factory is used in Gember Event Sourcing. 
However, if the ULID factory is preferred, it can be overwritten by configuration (`gember_event_sourcing.yaml`):
```yaml
gember_event_sourcing:
  generator:
    identity:
      # Use Gember alias of @Symfony\Component\Uid\Factory\UuidFactory:
      service: '@gember.identity_generator_symfony.uuid.symfony_uuid_identity_generator'

      # Or use Gember alias of @Symfony\Component\Uid\Factory\UlidFactory:
      # service: '@gember.identity_generator_symfony.ulid.symfony_ulid_identity_generator'
```

## Installation
When all prerequisites are met, it's time to install the bundle package itself:

```bash
composer require gember/event-sourcing-symfony-bundle 
```

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