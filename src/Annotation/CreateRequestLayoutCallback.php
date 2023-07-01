<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class CreateRequestLayoutCallback implements CacheableAnnotation
{
    public function toArray(): array
    {
        return [];
    }
}
