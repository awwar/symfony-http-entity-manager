<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class UpdateLayout implements CacheableAnnotation
{
    public function toArray(): array
    {
        return [];
    }
}
