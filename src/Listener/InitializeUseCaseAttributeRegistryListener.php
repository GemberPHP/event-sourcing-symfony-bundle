<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle\Listener;

use Gember\EventSourcing\UseCase\UseCaseAttributeRegistry;
use Gember\EventSourcing\Resolver\UseCase\DomainTagProperties\DomainTagsPropertiesResolver;
use Gember\EventSourcing\Resolver\UseCase\SubscriberMethodForEvent\SubscriberMethodForEventResolver;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Override;

final readonly class InitializeUseCaseAttributeRegistryListener implements EventSubscriberInterface
{
    public function __construct(
        private DomainTagsPropertiesResolver $domainTagPropertiesResolver,
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
        UseCaseAttributeRegistry::initialize(
            $this->domainTagPropertiesResolver,
            $this->subscriberMethodForEventResolver,
        );
    }
}
