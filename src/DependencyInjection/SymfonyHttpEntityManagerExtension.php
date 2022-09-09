<?php

declare(strict_types=1);

namespace Awwar\SymfonyHttpEntityManager\DependencyInjection;

use Awwar\SymfonyHttpEntityManager\Service\EntityMetadataObtainer;
use Awwar\SymfonyHttpEntityManager\Service\HttpEntitiesDiscovery;
use Awwar\SymfonyHttpEntityManager\Service\UOW\MetadataRegistry;
use ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SymfonyHttpEntityManagerExtension extends Extension
{
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
        $obtainer = new EntityMetadataObtainer();

        $entityClasses = [];
        $metadataMap = array_map(function (ReflectionClass $reflection) use ($obtainer, &$entityClasses) {
            $metadata = $obtainer->fromReflection($reflection);
            $entityClasses [] = $metadata['name'];
            return $metadata;
        }, $discovery->getData());

        $metadataRegistryService = new Definition(MetadataRegistry::class);
        $metadataRegistryService->setArguments([$metadataMap]);
        $metadataRegistryService->setAutoconfigured(true);
        $metadataRegistryService->setAutowired(true);
        $metadataRegistryService->setLazy(true);

        $container->setDefinition(MetadataRegistry::class, $metadataRegistryService);

        $container->setParameter('http_entity.proxy_dir', '%kernel.cache_dir%/http_doctrine/Proxies/__HTTP__');
        $container->setParameter('http_entity.entity_classes', $entityClasses);
    }
}
