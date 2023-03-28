<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;
use Awwar\PhpHttpEntityManager\Enum\RequestEnum;

#[Attribute(Attribute::TARGET_CLASS)]
class UpdateMethod implements CacheableAnnotation
{
    public function __construct(
        private string $name = RequestEnum::METHOD_PATCH,
        private bool $useDiff = true,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'use_diff' => $this->useDiff,
        ];
    }

    public static function getDefault(): array
    {
        return [
            'target'     => Attribute::TARGET_CLASS,
            'targetName' => null,
            'data'       => [
                'name'     => RequestEnum::METHOD_PATCH,
                'use_diff' => true,
            ],
        ];
    }
}
