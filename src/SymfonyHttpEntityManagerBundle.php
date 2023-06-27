<?php

declare(strict_types=1);

namespace Awwar\SymfonyHttpEntityManager;

use Awwar\SymfonyHttpEntityManager\Service\ProxyGenerator;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyHttpEntityManagerBundle extends Bundle
{
    public function boot()
    {
        parent::boot();

        $generator = new ProxyGenerator(
            (string) $this->container->getParameter('http_entity.proxy_dir')
        );

        $autoloader = static function ($className) use ($generator) {
            if (!str_contains($className, $generator->getProxyNamespace())) {
                return;
            }

            $className = str_replace($generator->getProxyNamespace(), '', $className);

            $classPath = sprintf('%s/%s.php', $generator->getCachePath(), $className);

            require str_replace('\\', '/', $classPath);
        };

        spl_autoload_register($autoloader, prepend: true);

        $entityClasses = $this->container->getParameter('http_entity.entity_classes');

        foreach ($entityClasses as $entityClass) {
            $generator->generate($entityClass);
        }
    }
}
