<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Closure;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\CreateLayout;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\DefaultValue;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\EntityId;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\FieldMap;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\FilterOneQuery;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\FilterQuery;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\GetOneQuery;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\HttpEntity;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\ListDetermination;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\RelationMap;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\RelationMapper;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\UpdateLayout;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\UpdateMethod;
use ReflectionClass;

class EntityMetadataObtainer
{
    private array $expectedAttributes;

    public function __construct()
    {
        $this->expectedAttributes = [
            HttpEntity::class        => HttpEntity::getDefault(),
            EntityId::class          => EntityId::getDefault(),
            CreateLayout::class      => CreateLayout::getDefault(),
            UpdateLayout::class      => UpdateLayout::getDefault(),
            UpdateMethod::class      => UpdateMethod::getDefault(),
            FieldMap::class          => FieldMap::getDefault(),
            FilterQuery::class       => FilterQuery::getDefault(),
            FilterOneQuery::class    => FilterOneQuery::getDefault(),
            GetOneQuery::class       => GetOneQuery::getDefault(),
            RelationMap::class       => RelationMap::getDefault(),
            RelationMapper::class    => RelationMapper::getDefault(),
            ListDetermination::class => ListDetermination::getDefault(),
            DefaultValue::class      => DefaultValue::getDefault(),
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

        foreach ($this->expectedAttributes as $expected => $default) {
            if (false === empty($default) && empty($result['attribute'][$expected])) {
                $result['attribute'][$expected][] = $default;
            }
        }

        return $result;
    }

    private function processAttribute(array &$result, string $targetName, Closure $attributeAccessor): void
    {
        foreach ($this->expectedAttributes as $expected => $default) {
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
            'data'       => $data,
        ];
    }
}
