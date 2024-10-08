<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;
use Awwar\PhpHttpEntityManager\Metadata\RelationSettings;

#[Attribute(Attribute::TARGET_PROPERTY)]
class RelationField implements CacheableAnnotation
{
    public const ONE = RelationSettings::ONE;

    public const MANY = RelationSettings::MANY;

    public function __construct(
        private string $class,
        private int $expects,
        private string $alias = "",
        //private ?string $lateUrl = null
    )
    {
    }

    public function toArray(): array
    {
        return [
            'class'   => $this->class,
            'name'    => $this->alias,
            'expects' => $this->expects,
            //'lateUrl' => $this->lateUrl,
        ];
    }
}
