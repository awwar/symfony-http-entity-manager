<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class CreateLayout implements CacheableAnnotation
{
    public function toArray(): array
    {
        return [];
    }

    public static function getDefault(): array
    {
        return [
            'target'     => Attribute::TARGET_METHOD,
            'targetName' => null,
            'data'       => [],
        ];
    }
}
