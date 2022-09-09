<?php

namespace Awwar\SymfonyHttpEntityManager\Exception;

class IdentityNotFoundException extends \Exception
{
    public static function create(string $className, ?string $id): self
    {
        return new self(sprintf("Identity of class %s with id %s not found", $className, $id));
    }

    public static function createFromClassName(string $className): self
    {
        return new self(sprintf("Identity of class %s not found", $className));
    }
}
