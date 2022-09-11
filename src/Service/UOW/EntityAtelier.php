<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

class EntityAtelier
{
    public function __construct(private MetadataRegistryInterface $metadataRegistry)
    {
    }

    public function suitUpEntity(object $entity): SuitedUpEntity
    {
        $metadata = $this->metadataRegistry->get(get_class($entity));

        return SuitedUpEntity::create($entity, $metadata);
    }

    public function suitUpClass(string $entityClass): SuitedUpEntity
    {
        $metadata = $this->metadataRegistry->get($entityClass);

        return SuitedUpEntity::create($metadata->getEmptyInstance(), $metadata);
    }
}
