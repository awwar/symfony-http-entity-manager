<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

interface RelationMapperInterface
{
    public function map(iterable $data, array $setting): ?object;
}
