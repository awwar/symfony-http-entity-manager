<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\Http\Exception\InvalidDataException;
use Awwar\SymfonyHttpEntityManager\Service\Http\Exception\NotFoundException;
use Awwar\SymfonyHttpEntityManager\Service\Http\Exception\NotProcessedException;
use Symfony\Component\HttpFoundation\Request;
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

    public function get(string $path, array $query = []): array
    {
        return $this->makeRequest(Request::METHOD_GET, $path, ['query' => $query]);
    }

    public function create(string $path, array $data = []): array
    {
        return $this->makeRequest(Request::METHOD_POST, $path, ['json' => $data]);
    }

    public function update(string $path, array $data = []): array
    {
        return $this->makeRequest($this->updateMethod, $path, ['json' => $data]);
    }

    public function delete(string $path): void
    {
        $this->makeRequest(Request::METHOD_DELETE, $path);
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
