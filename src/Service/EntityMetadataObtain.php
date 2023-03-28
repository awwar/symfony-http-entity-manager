<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\SymfonyHttpEntityManager\Annotation;
use Closure;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EntityMetadataObtain
{
    private array $expectedAttributes;

    public function __construct()
    {
        $this->expectedAttributes = [
            Annotation\HttpEntity::class        => Annotation\HttpEntity::getDefault(),
            Annotation\EntityId::class          => Annotation\EntityId::getDefault(),
            Annotation\CreateLayout::class      => Annotation\CreateLayout::getDefault(),
            Annotation\UpdateLayout::class      => Annotation\UpdateLayout::getDefault(),
            Annotation\UpdateMethod::class      => Annotation\UpdateMethod::getDefault(),
            Annotation\DataField::class         => Annotation\DataField::getDefault(),
            Annotation\RelationField::class     => Annotation\RelationField::getDefault(),
            Annotation\FilterQuery::class       => Annotation\FilterQuery::getDefault(),
            Annotation\FilterOneQuery::class    => Annotation\FilterOneQuery::getDefault(),
            Annotation\GetOneQuery::class       => Annotation\GetOneQuery::getDefault(),
            Annotation\RelationMapper::class    => Annotation\RelationMapper::getDefault(),
            Annotation\ListDetermination::class => Annotation\ListDetermination::getDefault(),
            Annotation\DefaultValue::class      => Annotation\DefaultValue::getDefault(),
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
            'data'       => $this->preprocessData($data, $class),
        ];
    }

    private function preprocessData(array $data, string $attributeClass): array
    {
        if ($attributeClass === Annotation\HttpEntity::class) {
            $data['client'] = new Reference($data['client'] ?? HttpClientInterface::class);
            $repository = $data['repository'] ?? null;
            $data['repository'] = $repository === null ? null : new Reference($repository);
        }

        return $data;
    }
}
