<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\UOW\RelationMapping;

interface RelationMapperInterface
{
    public function map(iterable $data, RelationMapping $mapping): ?object;
}
