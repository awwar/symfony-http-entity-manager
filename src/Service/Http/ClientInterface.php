<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

interface ClientInterface
{
    public function get(string $path, array $query = []): array;

    public function create(string $path, array $data = []): array;

    public function update(string $path, array $data = []): array;

    public function delete(string $path): void;
}
