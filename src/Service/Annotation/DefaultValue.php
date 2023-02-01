<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DefaultValue implements CacheableAnnotation
{
    public function __construct(
        private int|string|bool|array|float|null $value = EmptyValue::class,
        private ?array $callback = null
    ) {
    }

    public static function getDefault(): array
    {
        return [
            'target'     => Attribute::TARGET_PROPERTY,
            'targetName' => null,
            'data'       => [
                'value' => EmptyValue::class,
            ],
        ];
    }

    public function toArray(): array
    {
        $value = EmptyValue::class;

        if ($this->value !== EmptyValue::class) {
            $value = $this->value;
        } elseif ($this->value !== null) {
            $value = call_user_func($this->callback);
        }

        return [
            'value' => $value,
        ];
    }
}
