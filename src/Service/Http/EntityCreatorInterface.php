<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\UOW\RelationMapping;

interface EntityCreatorInterface
{
    public function createEntityWithData(string $className, mixed $data): ?object;
}
