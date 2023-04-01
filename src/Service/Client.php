<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\PhpHttpEntityManager\Client\ClientInterface;
use Awwar\PhpHttpEntityManager\Enum\RequestEnum;
use Awwar\PhpHttpEntityManager\Exception\InvalidDataException;
use Awwar\PhpHttpEntityManager\Exception\NotFoundException;
use Awwar\PhpHttpEntityManager\Exception\NotProcessedException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
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
        return $this->makeRequest(RequestEnum::METHOD_POST, $path, ['json' => $data]);
    }

    public function delete(string $path, array $query = []): void
    {
        $this->makeRequest(RequestEnum::METHOD_DELETE, $path, ['query' => $query]);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->makeRequest(RequestEnum::METHOD_GET, $path, ['query' => $query]);
    }

    public function update(string $path, array $data = []): array
    {
        return $this->makeRequest($this->updateMethod, $path, ['json' => $data]);
    }

    private function makeRequest(string $method, string $path, array $context = []): array
    {
        try {
            if ($method === RequestEnum::METHOD_DELETE) {
                $this->client->request($method, $path, $context)->getContent();

                return [];
            } else {
                return $this->client->request($method, $path, $context)->toArray();
            }
        } catch (
            TransportExceptionInterface
            | RedirectionExceptionInterface
            | DecodingExceptionInterface
            | ServerExceptionInterface $e
        ) {
            throw new NotProcessedException(entity: $this->entityName, previous: $e);
        } catch (ClientExceptionInterface $e) {
            if ($e->getCode() === 404) {
                throw new NotFoundException(entity: $this->entityName, previous: $e);
            }
            throw new InvalidDataException(entity: $this->entityName, previous: $e);
        }
    }
}
