<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\PhpHttpEntityManager\Annotation;
use Awwar\PhpHttpEntityManager\UOW\MetadataDTO;
use Awwar\PhpHttpEntityManager\UOW\MetadataRegistry;
use Awwar\PhpHttpEntityManager\UOW\MetadataRegistryInterface;

class MetadataRegistryFactory
{
    public static function create(array $settings): MetadataRegistryInterface
    {
        $metadataDTOS = array_map(fn (array $data) => self::mapData($data), $settings);

        return new MetadataRegistry($metadataDTOS);
    }

    private static function mapData(array $data): MetadataDTO
    {
        $className = $data['name'];

        $annotations = $data['attribute'];

        $proxyClass = ProxyGenerator::PROXY_NAMESPACE . "{$className}Proxy";

        /** @var class-string $proxyClass */
        $proxyClass = str_replace('/', '\\', $proxyClass);

        $metadata = $annotations[Annotation\HttpEntity::class][0]['data'];

        return new MetadataDTO(
            entityClassName: $className,
            proxyClassName: $proxyClass,
            idProperty: $annotations[Annotation\EntityId::class][0]['targetName'] ?? null,
            updateMethod: $annotations[Annotation\UpdateMethod::class][0]['data']['name'] ?? '',
            useDiffOnUpdate: (bool) $annotations[Annotation\UpdateMethod::class][0]['data']['use_diff'],
            filterQuery: (array) $annotations[Annotation\FilterQuery::class][0]['data'],
            getOneQuery: (array) $annotations[Annotation\GetOneQuery::class][0]['data'],
            filterOneQuery: (array) $annotations[Annotation\FilterOneQuery::class][0]['data'],
            name: $metadata['name'],
            httpClient: $metadata['client'],
            repository: $metadata['repository'],
            getOnePattern: (string) $metadata['one'],
            getListPattern: (string) $metadata['list'],
            createPattern: (string) $metadata['create'],
            updatePattern: (string) $metadata['update'],
            deletePattern: (string) $metadata['delete'],
            dataFields: $annotations[Annotation\DataField::class] ?? [],
            relationFields: $annotations[Annotation\RelationField::class] ?? [],
            defaultValues: $annotations[Annotation\DefaultValue::class] ?? [],
            relationMapperMethod: (string) $annotations[Annotation\RelationMapper::class][0]['targetName'],
            createLayoutMethod: (string) $annotations[Annotation\CreateLayout::class][0]['targetName'],
            updateLayoutMethod: (string) $annotations[Annotation\UpdateLayout::class][0]['targetName'],
            listDeterminationMethod: (string) $annotations[Annotation\ListDetermination::class][0]['targetName'],
        );
    }
}