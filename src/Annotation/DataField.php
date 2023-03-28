<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DataField implements CacheableAnnotation
{
    public function __construct(
        private ?string $all = null,
        private ?string $pre = EmptyValue::class,
        private ?string $post = EmptyValue::class,
        private ?string $preCreate = EmptyValue::class,
        private ?string $postCreate = EmptyValue::class,
        private ?string $preUpdate = EmptyValue::class,
        private ?string $postUpdate = EmptyValue::class,
        private ?string $postRead = EmptyValue::class,
    ) {
    }

    public function toArray(): array
    {
        $this->pre = $this->pre === EmptyValue::class ? $this->all : $this->pre;
        $this->post = $this->post === EmptyValue::class ? $this->all : $this->post;

        return [
            'beforeCreate' => $this->preCreate === EmptyValue::class ? $this->pre : $this->preCreate,
            'afterCreate'  => $this->postCreate === EmptyValue::class ? $this->post : $this->postCreate,
            'afterRead'    => $this->postRead === EmptyValue::class ? $this->post : $this->postRead,
            'beforeUpdate' => $this->preUpdate === EmptyValue::class ? $this->pre : $this->preUpdate,
            'afterUpdate'  => $this->postUpdate === EmptyValue::class ? $this->post : $this->postUpdate,
        ];
    }

    public static function getDefault(): array
    {
        return [
            'target'     => Attribute::TARGET_PROPERTY,
            'targetName' => null,
            'data'       => [
                'beforeCreate' => null,
                'afterCreate'  => null,
                'afterRead'    => null,
                'beforeUpdate' => null,
                'afterUpdate'  => null,
            ],
        ];
    }
}
