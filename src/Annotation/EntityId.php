<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class EntityId implements CacheableAnnotation
{
    public function toArray(): array
    {
        return [];
    }
}
