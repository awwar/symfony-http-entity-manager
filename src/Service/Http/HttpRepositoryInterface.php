<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Generator;

interface HttpRepositoryInterface
{
    public function find(mixed $id, array $criteria = []): object;

    public function filter(array $filter = []): Generator;

    public function filterOne(array $filter = []): object;

    public function add(object $object, bool $flush = false): void;

    public function remove(object $object, bool $flush = false): void;
}
