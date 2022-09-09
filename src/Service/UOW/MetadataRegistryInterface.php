<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

interface MetadataRegistryInterface
{
    public function get(string $className): EntityMetadata;
}
