<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class FilterQuery implements CacheableAnnotation
{
    public function __construct(private array $query = [], private array $callback = [], private array $args = [])
    {
    }

    public function toArray(): array
    {
        if (false === empty($this->query)) {
            return $this->query;
        }
        return call_user_func($this->callback, ...$this->args);
    }

    public static function getDefault(): array
    {
        return [
            'target'     => Attribute::TARGET_CLASS,
            'targetName' => null,
            'data'       => [],
        ];
    }
}
