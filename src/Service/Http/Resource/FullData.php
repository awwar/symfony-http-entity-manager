<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http\Resource;

class FullData
{
    public function __construct(private array $data)
    {
    }

    public function getData(): array
    {
        return $this->data;
    }
}
