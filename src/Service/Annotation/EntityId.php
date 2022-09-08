<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class EntityId implements CacheableAnnotation
{
    public function toArray(): array
    {
        return [];
    }

    public static function getDefault(): array
    {
        return [
            'target'     => Attribute::TARGET_PROPERTY,
            'targetName' => null,
            'data'       => [],
        ];
    }
}
