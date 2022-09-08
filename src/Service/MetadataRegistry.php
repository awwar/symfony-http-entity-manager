<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Exception;

class MetadataRegistry
{
    private array $metadataMap = [];
    private array $proxyAliases = [];

    /**
     * @throws Exception
     */
    public function __construct(array $metadataMap = [])
    {
        foreach ($metadataMap as $data) {
            $metadata = new EntityMetadata($data['name'], $data['attribute']);

            $proxyClass = $metadata->getProxyClass();
            $originalClass = $metadata->getClassName();

            $this->proxyAliases[$proxyClass] = $originalClass;
            $this->metadataMap[$originalClass] = $metadata;
        }
    }

    /**
     * @throws Exception
     */
    public function get(string $className): EntityMetadata
    {
        if (isset($this->proxyAliases[$className])) {
            $className = $this->proxyAliases[$className];
        }

        if (false === isset($this->metadataMap[$className])) {
            throw new Exception("Unable to find client for $className");
        }

        return $this->metadataMap[$className];
    }
}
