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

    public function cleanupHttpEntityManager(): void
    {
        $this->entityManager->clear();
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::TERMINATE => 'cleanupHttpEntityManager',
        ];

        if (class_exists('Symfony\Component\Messenger\Event\WorkerMessageHandledEvent', true)) {
            $events['Symfony\Component\Messenger\Event\WorkerMessageHandledEvent'] = 'cleanupHttpEntityManager';
        }

        if (class_exists('Symfony\Component\Messenger\Event\WorkerMessageFailedEvent', true)) {
            $events['Symfony\Component\Messenger\Event\WorkerMessageFailedEvent'] = 'cleanupHttpEntityManager';
        }

        return $events;
    }
}
