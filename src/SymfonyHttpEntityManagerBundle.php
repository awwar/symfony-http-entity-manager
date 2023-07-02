<?php

declare(strict_types=1);

namespace Awwar\SymfonyHttpEntityManager;

use Awwar\PhpHttpEntityManager\EntityManager\HttpEntityManagerInterface;
use Awwar\SymfonyHttpEntityManager\Service\ProxyGenerator;
use Closure;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyHttpEntityManagerBundle extends Bundle
{
    private ?Closure $autoloader;

    public function boot(): void
    {
        parent::boot();

        $generator = new ProxyGenerator(
            (string) $this->container->getParameter('http_entity.proxy_dir')
        );

        $this->autoloader = static function ($className) use ($generator) {
            if (!str_contains($className, $generator->getProxyNamespace())) {
                return;
            }

            $className = str_replace($generator->getProxyNamespace(), '', $className);

            $classPath = sprintf('%s/%s.php', $generator->getCachePath(), $className);

            require str_replace('\\', '/', $classPath);
        };

        spl_autoload_register($this->autoloader, prepend: true);

        $entityClasses = $this->container->getParameter('http_entity.entity_classes');

        foreach ($entityClasses as $entityClass) {
            $generator->generate($entityClass);
        }
    }

    public function shutdown(): void
    {
        if ($this->autoloader !== null) {
            spl_autoload_unregister($this->autoloader);
            $this->autoloader = null;
        }

        if ($this->container->initialized(HttpEntityManagerInterface::class)) {
            /** @var HttpEntityManagerInterface $em */
            $em = $this->container->get(HttpEntityManagerInterface::class);

            $em->clear();
        }
    }
}
