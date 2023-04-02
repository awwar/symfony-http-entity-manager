<?php

namespace Awwar\SymfonyHttpEntityManager\EventSubscriber;

use Awwar\PhpHttpEntityManager\EntityManager\HttpEntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelEventsSubscriber implements EventSubscriberInterface
{
    public function __construct(private HttpEntityManagerInterface $entityManager)
    {
    }

    public function clearEm(): void
    {
        $this->entityManager->clear();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'clearEm',
        ];
    }
}
