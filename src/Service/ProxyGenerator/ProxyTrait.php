<?php

namespace Awwar\SymfonyHttpEntityManager\Service\ProxyGenerator;

use Closure;

trait ProxyTrait
{
    private bool $__initialized = false;

    //private bool $__late_proxy = false;

    private string $__id = '';

    private Closure $__manager;

    private Closure $__getter;

    private Closure $__setter;

    public function __get(string $name)
    {
        if (($name !== $this->__id /*|| $this->__late_proxy*/) && $this->__initialized === false) {
            $this->__initialized = true;
            call_user_func($this->__manager, $this);
        }

        return $this->__getter->call($this, $this, $name);
    }

    public function __set(string $name, $value): void
    {
        if (($name !== $this->__id /*|| $this->__late_proxy*/) && $this->__initialized === false) {
            $this->__initialized = true;
            call_user_func($this->__manager, $this);
        }

        $this->__setter->call($this, $this, $name, $value);
    }

    public function __clone()
    {
    }

    private function __prepare(string $idProperty, array $properties, Closure $manager, bool $lateProxy = false): void
    {
        $parentClass = get_parent_class($this);

        $unsetter = Closure::bind(function (object $parent, string $name) {
            unset($parent->$name);
        }, null, $parentClass);

        foreach ($properties as $property) {
            if ($property === $idProperty) {
                continue;
            }

            $unsetter($this, $property);
        }

        $this->__id = $idProperty;
        $this->__manager = $manager;
        //$this->__late_proxy = $lateProxy;

        $this->__getter = Closure::bind(function (object $parent, string $name) {
            return $parent->$name;
        }, null, $parentClass);

        $this->__setter = Closure::bind(function (object $parent, string $name, mixed $value) {
            $parent->$name = $value;
        }, null, $parentClass);
    }
}
