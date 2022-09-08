<?php

declare(strict_types=1);

namespace Awwar\SymfonyHttpEntityManager;

use Awwar\SymfonyHttpEntityManager\Service\ProxyGenerator\Generator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyHttpEntityManagerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }

    public function boot()
    {
        parent::boot();

        $generator = new Generator(
            $this->container->getParameter('http_entity.proxy_dir')
        );

        $autoloader = static function ($className) use ($generator) {
            if (!str_contains($className, $generator->getProxyNamespace())) {
                return;
            }

            $className = str_replace($generator->getProxyNamespace(), '', $className);

            require str_replace('\\', '/', $generator->getCachePath() . '/' . $className . '.php');
        };

        spl_autoload_register($autoloader, prepend: true);

        $entityClasses = $this->container->getParameter('http_entity.entity_classes');

        foreach ($entityClasses as $entityClass) {
            $generator->generate($entityClass);
        }
    }
}
