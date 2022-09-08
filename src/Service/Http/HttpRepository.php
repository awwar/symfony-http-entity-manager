<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Generator;

class HttpRepository implements HttpRepositoryInterface
{
    public function __construct(
        private HttpEntityManagerInterface $httpEntityManager,
        private string $entityClass
    ) {
    }

    public function find(mixed $id, array $criteria = []): object
    {
        return $this->httpEntityManager->find($this->entityClass, $id, $criteria);
    }

    public function filter(array $filter = []): Generator
    {
        return $this->httpEntityManager->filter($this->entityClass, $filter);
    }

    public function filterOne(array $filter = []): object
    {
        return $this->httpEntityManager->filter($this->entityClass, $filter, isFilterOne: true)->current();
    }

    public function add(object $object, bool $flush = false): void
    {
        $this->httpEntityManager->persist($object);

        if ($flush) {
            $this->httpEntityManager->flush();
        }
    }

    public function remove(object $object, bool $flush = false): void
    {
        $this->httpEntityManager->remove($object);

        if ($flush) {
            $this->httpEntityManager->flush();
        }
    }
}
