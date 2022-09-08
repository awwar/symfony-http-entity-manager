<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http\Resource;

class Reference
{
    public function __construct(private string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
