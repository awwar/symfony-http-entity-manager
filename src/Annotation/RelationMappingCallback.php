<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RelationMappingCallback implements CacheableAnnotation
{
    public function __construct()
    {
    }

    public function toArray(): array
    {
        return [];
    }
}
