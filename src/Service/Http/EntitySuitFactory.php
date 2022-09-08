<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\MetadataRegistry;

class EntitySuitFactory
{
    public function __construct(private MetadataRegistry $metadataRegistry)
    {
    }

    public function create(object $entity): EntitySuit
    {
        $metadata = $this->metadataRegistry->get(get_class($entity));

        return EntitySuit::create($entity, $metadata);
    }

    public function createDirty(object $entity): EntitySuit
    {
        $metadata = $this->metadataRegistry->get(get_class($entity));

        return EntitySuit::createDirty($entity, $metadata);
    }

    public function createFromClass(string $entityClass): EntitySuit
    {
        $metadata = $this->metadataRegistry->get($entityClass);

        return EntitySuit::createDirty($metadata->getEmptyInstance(), $metadata);
    }
}
