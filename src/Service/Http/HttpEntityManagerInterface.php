<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Generator;

interface HttpEntityManagerInterface
{
    public function find(string $className, mixed $id, array $criteria = []): object;

    public function persist(object $object): void;

    public function remove(object $object): void;

    public function clear(string $objectName = null): void;

    public function detach(object $object): void;

    public function refresh(object $object): void;

    public function merge(object $object): void;

    public function flush(): void;

    public function getRepository(string $className): HttpRepositoryInterface;

    public function iterate(
        string $className,
        array $criteria,
        ?string $url = null,
        bool $isFilterOne = false
    ): Generator;

    public function contains(object $object): bool;
}
