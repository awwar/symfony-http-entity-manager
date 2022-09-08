<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Annotation;

interface CacheableAnnotation
{
    public function toArray(): array;

    public static function getDefault(): array;
}
