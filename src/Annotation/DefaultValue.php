<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DefaultValue implements CacheableAnnotation
{

    public function __construct(
        private int|string|bool|array|float|null $value = EmptyValue::class,
        private ?array $callback = null
    ) {
    }

    public function toArray(): array
    {
        $value = EmptyValue::class;

        if ($this->value !== EmptyValue::class) {
            $value = $this->value;
        } elseif ($this->callback !== null) {
            $value = call_user_func($this->callback);
        }

        return [
            'value' => $value,
        ];
    }

}
