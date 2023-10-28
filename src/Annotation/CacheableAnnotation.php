<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

interface CacheableAnnotation
{
    public function toArray(): array;
}
