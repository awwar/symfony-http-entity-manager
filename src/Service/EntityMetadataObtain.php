<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\SymfonyHttpEntityManager\Annotation;
use Closure;
use ReflectionClass;
use Symfony\Component\DependencyInjection\RelationReference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EntityMetadataObtain
{
    private array $expectedAttributes;

    public function __construct()
    {
        $this->expectedAttributes = [
            Annotation\HttpEntity::class,
            Annotation\EntityId::class,
            Annotation\CreateRequestLayoutCallback::class,
            Annotation\UpdateRequestLayoutCallback::class,
            Annotation\UpdateMethod::class,
            Annotation\DataField::class,
            Annotation\RelationField::class,
            Annotation\OnFilterQueryMixin::class,
            Annotation\OnFindOneQueryMixin::class,
            Annotation\OnGetOneQueryMixin::class,
            Annotation\RelationMappingCallback::class,
            Annotation\ListMappingCallback::class,
            Annotation\DefaultValue::class,
        ];
    }

    public function fromReflection(ReflectionClass $reflection): array
    {
        $result = [];
        $result['name'] = $reflection->getName();

        $this->processAttribute($result, $reflection->getName(), fn ($class) => $reflection->getAttributes($class));

        foreach ($reflection->getMethods() as $method) {
            $this->processAttribute($result, $method->getName(), fn ($class) => $method->getAttributes($class));
        }

        foreach ($reflection->getProperties() as $property) {
            $this->processAttribute($result, $property->getName(), fn ($class) => $property->getAttributes($class));
        }

        return $result;
    }

    private function processAttribute(array &$result, string $targetName, Closure $attributeAccessor): void
    {
        foreach ($this->expectedAttributes as $expected) {
            $attributes = $attributeAccessor($expected);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->putAttribute($result, $targetName, $attribute->getTarget(), $instance->toArray(), $expected);
            }
        }
    }

    private function putAttribute(array &$result, string $targetName, int $target, array $data, string $class): void
    {
        $result['attribute'][$class][] = [
            'target'     => $target,
            'targetName' => $targetName,
            'data'       => $this->preprocessData($data, $class),
        ];
    }

    private function preprocessData(array $data, string $attributeClass): array
    {
        if ($attributeClass === Annotation\HttpEntity::class) {
            $data['client'] = new RelationReference($data['client'] ?? HttpClientInterface::class);
            $repository = $data['repository'] ?? null;
            $data['repository'] = $repository === null ? null : new RelationReference($repository);
        }

        return $data;
    }
}
