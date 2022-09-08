<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class RelationMap implements CacheableAnnotation
{
    public const ONE = 0;
    public const MANY = 1;

    public function __construct(
        private string $class,
        private string $name,
        private int $expects,
        private ?string $lateUrl = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'class'   => $this->class,
            'name'    => $this->name,
            'expects' => $this->expects,
            'lateUrl' => $this->lateUrl,
        ];
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
