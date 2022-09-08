<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http\ListIterator;

class Data
{
    public function __construct(private array $data, private ?string $url)
    {
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
