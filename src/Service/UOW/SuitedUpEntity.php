<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

use Adbar\Dot;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\EmptyValue;
use Awwar\SymfonyHttpEntityManager\Service\Http\Collection\GeneralCollection;
use Awwar\SymfonyHttpEntityManager\Service\Http\EntityCreatorInterface;
use Closure;
use Exception;

class SuitedUpEntity
{
    private bool $isDeleted = false;
    private array|null $copy = null;

    private function __construct(
        private object $original,
        private EntityMetadata $entityMetadata
    ) {
    }

    public static function create(object $original, EntityMetadata $entityMetadata): self
    {
        return new self($original, $entityMetadata);
    }

    public function getOriginal(): object
    {
        return $this->original;
    }

    public function getMetadata(): EntityMetadata
    {
        return $this->entityMetadata;
    }

    public function getClass(): string
    {
        return get_class($this->original);
    }

    public function startWatch(): void
    {
        $this->copy = [
            'properties' => $this->getScalarSnapshot(),
            'relations'  => $this->getRelationSnapshot(),
        ];
    }

    public function getId(): ?string
    {
        return $this->getEntityId($this->original);
    }

    public function setId(mixed $id): void
    {
        $property = $this->entityMetadata->getIdProperty();

        $this->setValue($this->original, $property, $id);
    }

    public function delete(): void
    {
        $this->isDeleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getScalarChanges(): array
    {
        if ($this->copy === null) {
            throw new Exception("Got suit without copy!");
        }

        $actual = $this->getScalarSnapshot();
        $copy = $this->copy['properties'];

        $properties = $this->entityMetadata->getScalarProperties();

        $changes = [];

        foreach ($properties as $property) {
            // ToDo: покрыть тестами возможные кейсы
            $actualValue = $actual[$property] ?? null;
            $copyValue = $copy[$property] ?? null;

            if ($actualValue !== $copyValue) {
                $changes[$property] = $actualValue;
            }
        }

        return $changes;
    }

    public function getRelationChanges(): array
    {
        if ($this->copy === null) {
            throw new Exception("Got suit without copy!");
        }

        $relations = $this->entityMetadata->getRelationsMapping();

        $changes = [];

        foreach ($relations as $property => $mapping) {
            $copy = $this->copy['relations'][$property] ?? $mapping->getDefault();
            $original = $this->getRelationValue($this->original, $property, $mapping);

            $changes [$mapping->getName()] = [
                'original' => $original,
                'copy'     => $copy,
                'iterable' => $mapping->isCollection(),
            ];
        }

        $relationChanges = [];
        $relationDeleted = [];
        foreach ($changes as $name => $value) {
            $isIterable = $value['iterable'];
            $original = $value['original'];
            $copy = $value['copy'];
            if ($isIterable && is_iterable($original)) {
                //ToDo: тут только на добавление, нужно добавить на удаление
                foreach ($original as $originalEntity) {
                    $key = $this->getEntityIsNew($originalEntity)
                        ? $this->getEntitySplId($originalEntity)
                        : $this->getEntityUniqueId($originalEntity);
                    if (false === in_array($key, $copy)) {
                        // added
                        $relationChanges[$name][$key] = $originalEntity;
                    }
                }
            } else {
                if ($original !== null) {
                    $key = $this->getEntityUniqueId($original);

                    if ($key === $copy) {
                        continue;
                    }

                    $relationChanges[$name] = $original;
                } elseif ($copy !== null) {
                    $relationDeleted[$name] = true;
                }
            }
        }

        return $relationChanges;
    }

    public function getScalarSnapshot(): array
    {
        $properties = $this->entityMetadata->getScalarProperties();

        $snapshot = [];

        foreach ($properties as $property) {
            if ($property === $this->entityMetadata->getIdProperty()) {
                continue;
            }

            $snapshot[$property] = $this->getValue($this->original, $property);
        }

        return $snapshot;
    }

    public function getRelationSnapshot(): array
    {
        $snapshot = [];

        $relations = $this->entityMetadata->getRelationsMapping();

        foreach ($relations as $property => $mapping) {
            if (false === $this->issetProperty($this->original, $property)) {
                $snapshot[$property] = $mapping->getDefault();

                continue;
            }
            $relation = $this->getRelationValue($this->original, $property, $mapping);

            if (is_iterable($relation) && $mapping->isCollection()) {
                foreach ($relation as $subRelation) {
                    $snapshot[$property][] = $this->getEntityUniqueId($subRelation);
                }
            } else {
                $snapshot[$property] = $relation === null ? null : $this->getEntityUniqueId($relation);
            }
        }

        return $snapshot;
    }

    public function getRelationValues(): array
    {
        $relations = $this->entityMetadata->getRelationsMapping();

        $snapshot = [];

        foreach ($relations as $property => $mapping) {
            $snapshot[$mapping->getName()] = $this->getRelationValue($this->original, $property, $mapping);
        }

        return $snapshot;
    }

    public function isNew(): bool
    {
        return $this->getEntityIsNew($this->original);
    }

    public function getSPLId(): string
    {
        return $this->getEntitySplId($this->original);
    }

    public function getUniqueId(): string
    {
        return $this->getEntityUniqueId($this->original);
    }

    public function setIdAfterRead(array $data): void
    {
        $map = $this->entityMetadata->getFieldMap('afterRead');
        $idProperty = $this->entityMetadata->getIdProperty();
        $dot = new Dot($data);

        $this->setId($this->getByDot($dot, $map[$idProperty], $idProperty));
    }

    public function callAfterRead(array $data, EntityCreatorInterface $creator): void
    {
        $map = $this->entityMetadata->getFieldMap('afterRead');

        $this->mapScalarData($map, $data);

        $this->mapNestedRelation($data, $creator);
    }

    public function callAfterCreate(array $data): void
    {
        $map = $this->entityMetadata->getFieldMap('afterCreate');

        $this->mapScalarData($map, $data);
    }

    public function callAfterUpdate(array $data): void
    {
        $map = $this->entityMetadata->getFieldMap('afterUpdate');

        $this->mapScalarData($map, $data);
    }

    public function callBeforeCreate(array $entityData, array $relationData): array
    {
        $map = $this->entityMetadata->getFieldMap('beforeCreate');

        $layout = $this->entityMetadata->getCreateLayout()(
            $this->original,
            [],
            [],
            $entityData,
            $relationData
        );

        $dot = new Dot($layout);
        foreach ($map as $field => $path) {
            if (
                $field === $this->getMetadata()->getIdProperty()
                || $this->issetProperty($this->original, $field) === false
            ) {
                continue;
            }

            $dot->set($path, $this->getValue($this->original, $field));
        }

        return $dot->all();
    }

    public function callBeforeUpdate(
        array $entityChanges,
        array $relationChanges,
        array $entityData,
        array $relationData
    ): array {
        $map = $this->entityMetadata->getFieldMap('beforeUpdate');
        $layout = $this->entityMetadata->getUpdateLayout()(
            $this->original,
            $entityChanges,
            $relationChanges,
            $entityData,
            $relationData,
        );

        $dot = new Dot($layout);

        if ($this->entityMetadata->isUseDiffOnUpdate()) {
            foreach ($entityChanges as $field => $value) {
                $path = $map[$field] ?? null;
                if ($path === null) {
                    continue;
                }
                $dot->set($path, $value);
            }
        } else {
            foreach ($map as $field => $path) {
                if ($this->issetProperty($this->original, $field) === false) {
                    continue;
                }

                $dot->set($path, $this->getValue($this->original, $field));
            }
        }

        return $dot->all();
    }

    public function proxy(Closure $managerCallback, mixed $id = null): void
    {
        $this->original = $this->entityMetadata->getProxy();

        $callback = function ($idProperty, $properties) use ($managerCallback, $id) {
            $this->__prepare($idProperty, $properties, $managerCallback, $id === null);
        };

        $metadata = $this->entityMetadata;

        $callback->call($this->original, $metadata->getIdProperty(), $metadata->getProperties());

        if ($id !== null) {
            $this->setId($id);
        }
    }

    public function isProxy(): bool
    {
        return $this->getClass() === $this->getMetadata()->getProxyClass();
    }

    //public function isLateProxy(): bool
    //{
    //    if (!$this->isProxy()) {
    //        return false;
    //    }
    //
    //    return $this->getValue($this->original, '__late_proxy');
    //}

    public function isProxyInitialized(): bool
    {
        if (!$this->isProxy()) {
            return true;
        }
        return $this->getValue($this->original, '__initialized');
    }

    private function getRelationValue(object $object, string $property, RelationMapping $mapping): mixed
    {
        $data = $this->getValue($object, $property);

        return $data ?? $mapping->getDefault();
    }

    private function getEntityId(object $entity): ?string
    {
        $property = $this->entityMetadata->getIdProperty();

        try {
            return $this->getValue($entity, $property);
        } catch (\Throwable) {
            return null;
        }
    }

    private function getEntityIsNew(object $entity): bool
    {
        //if ($this->isLateProxy()) {
        //    return true;
        //}

        return $this->getEntityId($entity) === null;
    }

    private function getEntityUniqueId(object $entity): string
    {
        if ($this->getEntityIsNew($entity)) {
            throw new Exception("Unable to get uniqueId when entity is new!");
        }

        return sha1($this->getEntityId($entity) . get_class($entity));
    }

    private function getEntitySplId(object $entity): string
    {
        return (string) spl_object_id($entity);
    }

    private function mapScalarData(array $map, array $data): void
    {
        $dot = new Dot($data);

        foreach ($map as $field => $path) {
            $this->setValue($this->original, $field, $this->getByDot($dot, $path, $field));
        }
    }

    private function mapNestedRelation(array $data, EntityCreatorInterface $creator): void
    {
        $relationsMapper = $this->entityMetadata->getRelationsMapper();
        $relations = $this->entityMetadata->getRelationsMapping();

        foreach ($relations as $field => $mapping) {
            $mappedData = call_user_func($relationsMapper, $data, $mapping->getName());

            $value = $this->createNestedRelation($mapping, $mappedData, $creator);

            $this->setValue($this->original, $field, $value);
        }
    }

    private function createNestedRelation(
        RelationMapping $mapping,
        iterable $relationIterator,
        EntityCreatorInterface $creator
    ): ?object {
        $result = [];

        foreach ($relationIterator as $dataContainer) {
            $result[] = $creator->createEntityWithData($mapping->getClass(), $dataContainer);

            if ($mapping->isCollection() === false) {
                break;
            }
        }

        $result = array_filter($result);

        return $mapping->isCollection() ? new GeneralCollection($result) : array_pop($result);
    }

    private function setValue(object $object, string $property, mixed $value): void
    {
        $setter = function ($value) use ($property) {
            $this->{$property} = $value;
        };

        $setter->call($object, $value);
    }

    private function getValue(object $object, string $property): mixed
    {
        $setter = function () use ($property) {
            return $this->{$property};
        };

        return $setter->call($object);
    }

    private function issetProperty(object $object, string $property): bool
    {
        $setter = function () use ($property) {
            return property_exists($this, $property);
        };

        return $setter->call($object);
    }

    private function getByDot(Dot $dot, string $path, string $field): mixed
    {
        $default = $this->getMetadata()->getDefaultValue($field);

        if ($default === EmptyValue::class) {
            $default = null;
        }

        return $dot->get($path, $default);
    }
}
