<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle\Listener;

use Gember\EventSourcing\Resolver\UseCase\UseCaseResolver;
use Gember\EventSourcing\UseCase\UseCaseAttributeRegistry;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Override;

final readonly class InitializeUseCaseAttributeRegistryListener implements EventSubscriberInterface
{
    public function __construct(
        private UseCaseResolver $useCaseResolver,
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
        UseCaseAttributeRegistry::initialize($this->useCaseResolver);
    }
}
