<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

use Adbar\Dot;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\EmptyValue;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\RelationMap;
use Awwar\SymfonyHttpEntityManager\Service\Http\RelationMapperInterface;
use Closure;
use Exception;

class EntitySuit
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
        $suit = self::createDirty($original, $entityMetadata);

        $suit->startWatch();

        return $suit;
    }

    public static function createDirty(object $original, EntityMetadata $entityMetadata): self
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
        $changes = [
            'properties' => [],
            'relations' => []
        ];

        $properties = $this->getMetadata()->getProperties();
        $relations = $this->entityMetadata->getRelationsMap();

        foreach ($properties as $property) {
            if (isset($relations[$property])) {
                if (false === $this->issetProperty($this->original, $property)) {
                    $changes['relations'][$property] = $relations[$property]['expects'] === RelationMap::MANY
                        ? []
                        : null;

                    continue;
                }
                $relation = $this->getRelationValue($this->original, $property, $relations[$property]);

                if (false === is_iterable($relation)) {
                    $changes['relations'][$property] = $relation === null ? null : $this->getEntityUniqueId($relation);
                } else {
                    foreach ($relation as $subRelation) {
                        $changes['relations'][$property][] = $this->getEntityUniqueId($subRelation);
                    }
                }
            } else {
                if ($property === $this->getMetadata()->getIdProperty()) {
                    $value = $this->getEntityId($this->original);
                } else {
                    $value = $this->getValue($this->original, $property);
                }

                $changes['properties'][$property] = $value;
            }
        }

        $this->copy = $changes;
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

    public function isChanged(array $dataChanges, array $relationChanges): bool
    {
        return false === empty($dataChanges) || false === empty($relationChanges);
    }

    public function delete(): void
    {
        $this->isDeleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getEntityChanges(): array
    {
        if ($this->copy === null) {
            throw new Exception("Got suit without copy!");
        }

        $properties = $this->entityMetadata->getProperties();
        $relations = $this->entityMetadata->getRelationsMap();

        $changes = [];

        foreach ($properties as $property) {
            if (
                isset($relations[$property])
                || $this->issetProperty($this->original, $property) === false
            ) {
                continue;
            }

            $left = $this->getValue($this->original, $property);
            $right = $this->copy['properties'][$property];

            if ($left !== $right) {
                $changes[$property] = $left;
            }
        }

        return $changes;
    }

    public function getScalarValues(): array
    {
        $properties = $this->entityMetadata->getProperties();
        $relations = $this->entityMetadata->getRelationsMap();

        $changes = [];

        foreach ($properties as $property) {
            if (isset($relations[$property]) || $property === $this->entityMetadata->getIdProperty()) {
                continue;
            }

            $changes[$property] = $this->getValue($this->original, $property);
        }

        return $changes;
    }

    public function getRelationChanges(): array
    {
        if ($this->copy === null) {
            throw new Exception("Got suit without copy!");
        }

        $relations = $this->entityMetadata->getRelationsMap();

        $changes = [];

        foreach ($relations as $property => $data) {
            $isIterable = $data['expects'] === RelationMap::MANY;
            $copy = $this->copy['relations'][$property] ?? ($isIterable ? [] : null);
            $original = $this->getRelationValue($this->original, $property, $data);

            $changes [$data['name']] = [
                'original' => $original,
                'copy'     => $copy,
                'iterable' => $isIterable,
            ];
        }

        $relationChanges = [];
        $relationDeleted = [];
        foreach ($changes as $name => $value) {
            $original = $value['original'];
            $isIterable = $value['iterable'];
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

    public function getRelationValues(): array
    {
        if ($this->copy === null) {
            throw new Exception("Got suit without copy!");
        }

        $relations = $this->entityMetadata->getRelationsMap();

        $changes = [];

        foreach ($relations as $property => $data) {
            $changes [$data['name']] = $this->getRelationValue($this->original, $property, $data);
        }

        return $changes;
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

    public function callAfterRead(array $data, RelationMapperInterface $relationMapper): void
    {
        $map = $this->entityMetadata->getFieldMap('afterRead');

        $dot = new Dot($data);
        foreach ($map as $field => $path) {
            $this->setValue($this->original, $field, $this->getByDot($dot, $path, $field));
        }

        $relationsMapper = $this->entityMetadata->getRelationsMapper();
        $relations = $this->entityMetadata->getRelationsMap();

        foreach ($relations as $field => $payload) {
            $mappedData = call_user_func($relationsMapper, $data, $payload['name']);

            $value = $relationMapper->map($mappedData, $payload);

            $this->setValue($this->original, $field, $value);
        }
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

    public function callAfterCreate(array $data): void
    {
        $map = $this->entityMetadata->getFieldMap('afterCreate');

        $dot = new Dot($data);
        foreach ($map as $field => $path) {
            $this->setValue($this->original, $field, $this->getByDot($dot, $path, $field));
        }
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

    public function callAfterUpdate(array $data): void
    {
        $map = $this->entityMetadata->getFieldMap('afterUpdate');

        $dot = new Dot($data);
        foreach ($map as $field => $path) {
            $this->setValue($this->original, $field, $this->getByDot($dot, $path, $field));
        }
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

    private function getRelationValue(object $object, string $property, array $relationData): mixed
    {
        $data = $this->getValue($object, $property);

        return $data ?? ($relationData['expects'] === RelationMap::ONE ? null : []);
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
