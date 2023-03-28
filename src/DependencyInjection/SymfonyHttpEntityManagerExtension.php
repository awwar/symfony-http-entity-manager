<?php

declare(strict_types=1);

namespace Awwar\SymfonyHttpEntityManager\DependencyInjection;

use Awwar\PhpHttpEntityManager\Client\Client;
use Awwar\PhpHttpEntityManager\Client\ClientInterface;
use Awwar\PhpHttpEntityManager\Http\HttpEntityManager;
use Awwar\PhpHttpEntityManager\Http\HttpEntityManagerInterface;
use Awwar\PhpHttpEntityManager\Http\HttpRepository;
use Awwar\PhpHttpEntityManager\Http\HttpRepositoryInterface;
use Awwar\PhpHttpEntityManager\Metadata\EntityMetadata;
use Awwar\PhpHttpEntityManager\Metadata\MetadataRegistry;
use Awwar\PhpHttpEntityManager\Metadata\MetadataRegistryInterface;
use Awwar\PhpHttpEntityManager\UOW\EntityAtelier;
use Awwar\PhpHttpEntityManager\UOW\HttpUnitOfWork;
use Awwar\PhpHttpEntityManager\UOW\HttpUnitOfWorkInterface;
use Awwar\SymfonyHttpEntityManager\Service\EntityMetadataObtain;
use Awwar\SymfonyHttpEntityManager\Service\HttpEntitiesDiscovery;
use Awwar\SymfonyHttpEntityManager\Service\MetadataRegistryFactory;
use ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SymfonyHttpEntityManagerExtension extends Extension
{
    private const SERVICES = [
        HttpEntityManagerInterface::class => HttpEntityManager::class,
        HttpRepositoryInterface::class    => HttpRepository::class,
        ClientInterface::class            => Client::class,
        EntityAtelier::class              => EntityAtelier::class,
        EntityMetadata::class             => EntityMetadata::class,
        HttpUnitOfWorkInterface::class    => HttpUnitOfWork::class,
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $processedConfig = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.yaml');

        $mapping = [];

        $root = (string) $container->getParameter('kernel.project_dir');

        foreach ($processedConfig['entity_mapping'] ?? [] as $namespace => $config) {
            $mapping[$namespace] = $root . '/' . ($config['directory'] ?? '');
        }

        $discovery = new HttpEntitiesDiscovery($mapping);
        $obtain = new EntityMetadataObtain();

        $entityClasses = [];
        $metadataMap = array_map(function (ReflectionClass $reflection) use ($obtain, &$entityClasses) {
            $entityClasses [] = $reflection->getName();

            return $obtain->fromReflection($reflection);
        }, $discovery->searchEntries());

        $metadataRegistryService = (new Definition(MetadataRegistry::class))
            ->setFactory([MetadataRegistryFactory::class, 'create'])
            ->setArguments([$metadataMap])
            ->setAutoconfigured(true)
            ->setLazy(true);

        $container->setDefinition(MetadataRegistryInterface::class, $metadataRegistryService);

        foreach (self::SERVICES as $alias => $name) {
            $container->setDefinition(
                $alias,
                (new Definition($name))->setAutoconfigured(true)->setAutowired(true)
            );
        }

        $container->setParameter('http_entity.proxy_dir', '%kernel.cache_dir%/http_entity/Proxies/__HTTP__');
        $container->setParameter('http_entity.entity_classes', $entityClasses);
    }
}
