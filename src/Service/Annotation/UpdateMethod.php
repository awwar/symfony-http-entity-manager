<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Annotation;

use Attribute;
use Symfony\Component\HttpFoundation\Request;

#[Attribute(Attribute::TARGET_CLASS)]
class UpdateMethod implements CacheableAnnotation
{
    public function __construct(
        private string $name = Request::METHOD_PATCH,
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
                'name'     => Request::METHOD_PATCH,
                'use_diff' => true,
            ],
        ];
    }
}
