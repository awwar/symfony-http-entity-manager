<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\PhpHttpEntityManager\Client\ClientInterface;
use Awwar\PhpHttpEntityManager\Exception\InvalidDataException;
use Awwar\PhpHttpEntityManager\Exception\NotFoundException;
use Awwar\PhpHttpEntityManager\Exception\NotProcessedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client implements ClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private string $updateMethod,
        private string $entityName
    ) {
    }

    public function create(string $path, array $data = []): array
    {
        return $this->makeRequest(Request::METHOD_POST, $path, ['json' => $data]);
    }

    public function delete(string $path, array $query = []): void
    {
        $this->makeRequest(Request::METHOD_DELETE, $path, ['query' => $query]);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->makeRequest(Request::METHOD_GET, $path, ['query' => $query]);
    }

    public function update(string $path, array $data = []): array
    {
        return $this->makeRequest($this->updateMethod, $path, ['json' => $data]);
    }

    private function makeRequest(string $method, string $path, array $context = []): array
    {
        try {
            if ($method === Request::METHOD_DELETE) {
                $this->client->request($method, $path, $context)->getContent();

                return [];
            } else {
                return $this->client->request($method, $path, $context)->toArray();
            }
        } catch (ClientExceptionInterface $e) {
            if ($e->getCode() === 404) {
                throw new NotFoundException(entity: $this->entityName, previous: $e);
            }

            throw new InvalidDataException(entity: $this->entityName, previous: $e);
        } catch (ExceptionInterface $e) {
            throw new NotProcessedException(entity: $this->entityName, previous: $e);
        }
    }
}
