<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\PhpHttpEntityManager\Metadata\CallbacksSettings;
use Awwar\PhpHttpEntityManager\Metadata\EntityMetadata;
use Awwar\PhpHttpEntityManager\Metadata\FieldsSettings;
use Awwar\PhpHttpEntityManager\Metadata\FilterSettings;
use Awwar\PhpHttpEntityManager\Metadata\MetadataRegistry;
use Awwar\PhpHttpEntityManager\Metadata\MetadataRegistryInterface;
use Awwar\PhpHttpEntityManager\Metadata\RelationSettings;
use Awwar\PhpHttpEntityManager\Metadata\UrlSettings;
use Awwar\SymfonyHttpEntityManager\Annotation;
use Exception;
use InvalidArgumentException;
use ReflectionException;
use RuntimeException;

class MetadataRegistryFactory
{
    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public static function create(array $settings): MetadataRegistryInterface
    {
        $metadataMap = array_map(fn (array $data) => self::createMetadata($data), $settings);

        return new MetadataRegistry($metadataMap);
    }

    /**
     * @throws ReflectionException
     */
    private static function createMetadata(array $data): EntityMetadata
    {
        $className = $data['name'];

        $annotations = $data['attribute'];

        $proxyClass = ProxyGenerator::PROXY_NAMESPACE . "{$className}Proxy";

        /** @var class-string $proxyClass */
        $proxyClass = str_replace('/', '\\', $proxyClass);

        $generalEntityData = $annotations[Annotation\HttpEntity::class][0]['data'] ?? [];

        if (empty($generalEntityData)) {
            throw new RuntimeException(
                sprintf("Got %s entity without %s attribute", $className, Annotation\HttpEntity::class)
            );
        }

        $customName = $generalEntityData['name'] ?? $className;

        $idProperty = $annotations[Annotation\EntityId::class][0]['targetName'] ?? null;

        if ($idProperty === null) {
            throw new InvalidArgumentException(
                sprintf("Entity %s must contain id property with %s attribute", $className, Annotation\EntityId::class)
            );
        }

        $useDiffOnUpdate = (bool) $annotations[Annotation\UpdateMethod::class][0]['data']['use_diff'] ?? false;

        $callbacksSettings = new CallbacksSettings(
            relationMappingCallbackMethod: $annotations[Annotation\RelationMappingCallback::class][0]['targetName'] ?? null,
            createRequestLayoutCallbackMethod: $annotations[Annotation\CreateRequestLayoutCallback::class][0]['targetName'] ?? null,
            updateRequestLayoutCallbackMethod: $annotations[Annotation\UpdateRequestLayoutCallback::class][0]['targetName'] ?? null,
            listMappingCallbackMethod: $annotations[Annotation\ListMappingCallback::class][0]['targetName'] ?? null,
        );

        $urlSettings = new UrlSettings(
            one: (string) $generalEntityData['one'],
            list: (string) $generalEntityData['list'],
            create: (string) $generalEntityData['create'],
            update: (string) $generalEntityData['update'],
            delete: (string) $generalEntityData['delete'],
        );

        $filterSettings = new FilterSettings(
            onFilterQueryMixin: (array) ($annotations[Annotation\OnFilterQueryMixin::class][0]['data'] ?? []),
            onGetOneQueryMixin: (array) ($annotations[Annotation\OnGetOneQueryMixin::class][0]['data'] ?? []),
            onFindOneQueryMixin: (array) ($annotations[Annotation\OnFindOneQueryMixin::class][0]['data'] ?? []),
        );

        $fieldsSettings = new FieldsSettings((string) $idProperty);

        $dataFields = $annotations[Annotation\DataField::class] ?? [];

        foreach ($dataFields as $map) {
            foreach ($map['data'] as $condition => $path) {
                $fieldsSettings->addDataFieldMap($condition, $map['targetName'], $path);
            }
        }

        $relationFields = $annotations[Annotation\RelationField::class] ?? [];

        foreach ($relationFields as $map) {
            $settings = $map['data'];

            $fieldsSettings->addRelationField($map['targetName'], new RelationSettings(
                class: $settings['class'],
                name: $settings['name'],
                expects: $settings['expects']
            ));
        }

        $defaultValues = $annotations[Annotation\DefaultValue::class] ?? [];

        foreach ($defaultValues as $map) {
            $fieldsSettings->addDefaultValue($map['targetName'], $map['data']['value']);
        }

        $client = new Client(
            client: $generalEntityData['client'],
            updateMethod: $annotations[Annotation\UpdateMethod::class][0]['data']['name'] ?? 'PATCH',
            entityName: $customName
        );

        $repository = $generalEntityData['repository'] ?? null;

        return new EntityMetadata(
            entityClassName: $className,
            fieldsSettings: $fieldsSettings,
            client: $client,
            name: $customName,
            proxyClassName: $proxyClass,
            useDiffOnUpdate: $useDiffOnUpdate,
            filterSettings: $filterSettings,
            repository: $repository,
            urlSettings: $urlSettings,
            callbacksSettings: $callbacksSettings
        );
    }
}
