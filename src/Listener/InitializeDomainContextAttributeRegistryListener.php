<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle\Listener;

use Gember\EventSourcing\DomainContext\DomainContextAttributeRegistry;
use Gember\EventSourcing\DomainContext\EventSourcedDomainContext;
use Gember\EventSourcing\Resolver\DomainContext\DomainIdProperties\DomainIdPropertiesResolver;
use Gember\EventSourcing\Resolver\DomainContext\SubscriberMethodForEvent\SubscriberMethodForEventResolver;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Override;

/**
 * @template T of EventSourcedDomainContext
 */
final readonly class InitializeDomainContextAttributeRegistryListener implements EventSubscriberInterface
{
    /**
     * @param DomainIdPropertiesResolver<T> $domainIdPropertiesResolver
     * @param SubscriberMethodForEventResolver<T> $subscriberMethodForEventResolver
     */
    public function __construct(
        private DomainIdPropertiesResolver $domainIdPropertiesResolver,
        private SubscriberMethodForEventResolver $subscriberMethodForEventResolver,
    ) {}

    /**
     * @return array<string, string>
     */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onEvent',
            KernelEvents::REQUEST => 'onEvent',
        ];
    }

    public function onEvent(ConsoleCommandEvent|RequestEvent $event): void
    {
        DomainContextAttributeRegistry::initialize(
            $this->domainIdPropertiesResolver,
            $this->subscriberMethodForEventResolver,
        );
    }
}
