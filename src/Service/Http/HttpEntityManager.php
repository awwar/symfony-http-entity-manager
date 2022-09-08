<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\Http\Exception\NotFoundException;
use Awwar\SymfonyHttpEntityManager\Service\Http\ListIterator\Data;
use Awwar\SymfonyHttpEntityManager\Service\MetadataRegistry;
use Generator;
use Throwable;

class HttpEntityManager implements HttpEntityManagerInterface
{
    public function __construct(
        private HttpUnitOfWorkInterface $unitOfWork,
        private MetadataRegistry $metadataRegistry,
        private EntitySuitFactory $entitySuitFactory
    ) {
    }

    public function find(string $className, mixed $id, array $criteria = []): object
    {
        try {
            return $this->unitOfWork->getFromIdentity($id, $className)->getOriginal();
        } catch (Throwable) {
            $suit = $this->unitOfWork->newEntity($className);
            $metadata = $suit->getMetadata();

            $newCriteria = array_merge($metadata->getGetOneQuery(), $criteria);

            $data = $metadata->getClient()->get($metadata->getUrlForOne($id), $newCriteria);

            $suit->callAfterRead($data, new RelationMapper($this->unitOfWork, $this));

            $this->unitOfWork->commit($suit);

            return $this->unitOfWork->getFromIdentityBySuit($suit)->getOriginal();
        }
    }

    public function filter(string $className, array $criteria, bool $isFilterOne = false): Generator
    {
        $metadata = $this->metadataRegistry->get($className);

        $options = array_merge($isFilterOne ? $metadata->getFilterOneQuery() : $metadata->getFilterQuery(), $criteria);

        $entityIterator = $this->iterate($className, $options);
        $firstIteration = true;

        do {
            $entity = $entityIterator->current();

            if ($entity === null) {
                if ($firstIteration === true && $isFilterOne === true) {
                    throw new NotFoundException(entity: $metadata->getName());
                }
                $firstIteration = false;
                break;
            }

            yield $entity;

            $entityIterator->next();
        } while (true);
    }

    public function persist(object $object): void
    {
        $suit = $this->entitySuitFactory->createDirty($object);

        $this->unitOfWork->commit($suit);
    }

    public function flush(): void
    {
        $manipulations = $this->unitOfWork->getChanges();

        foreach ($manipulations as $manipulation) {
            $manipulation->execute();

            $suit = $manipulation->getSuit();

            $this->unitOfWork->upgrade($suit);
        }
    }

    public function remove(object $object): void
    {
        $suit = $this->entitySuitFactory->createDirty($object);

        $this->unitOfWork->delete($suit);
    }

    public function clear(string $objectName = null): void
    {
        $this->unitOfWork->clear($objectName);
    }

    public function detach(object $object): void
    {
        $suit = $this->entitySuitFactory->createDirty($object);

        $this->unitOfWork->remove($suit);
    }

    public function refresh(object $object): void
    {
        $suit = $this->entitySuitFactory->createDirty($object);

        $suit = $this->unitOfWork->getFromIdentityBySuit($suit);
        $metadata = $suit->getMetadata();

        $data = $metadata->getClient()->get(
            $metadata->getUrlForOne($suit->getId()),
            $metadata->getGetOneQuery()
        );

        $suit->callAfterRead($data, new RelationMapper($this->unitOfWork, $this));
    }

    public function getRepository(string $className): HttpRepositoryInterface
    {
        return $this->metadataRegistry->get($className)->getRepository() ?? new HttpRepository($this, $className);
    }

    public function contains(object $object): bool
    {
        $suit = $this->entitySuitFactory->createDirty($object);

        return $this->unitOfWork->hasEntity($suit);
    }

    public function merge(object $object): void
    {
        throw new \RuntimeException('Merge is not implemented!');
    }

    public function iterate(string $className, array $options, ?string $url = null): Generator
    {
        $metadata = $this->metadataRegistry->get($className);

        $data = $metadata->getClient()->get($url === null ? $metadata->getUrlForList() : $url, $options);

        $iterator = $metadata->getListDetermination()($data);

        $nextUrl = null;
        do {
            $signal = $iterator->current();

            if ($signal instanceof Data) {
                $nextUrl = $signal->getUrl();
            }

            if ($signal === null) {
                if ($nextUrl === null) {
                    break;
                }
                $data = $metadata->getClient()->get($nextUrl, []);

                $iterator = $metadata->getListDetermination()($data);
                $nextUrl = null;
                continue;
            }

            $newSuit = $this->unitOfWork->newEntity($className);
            $newSuit->callAfterRead($signal->getData(), new RelationMapper($this->unitOfWork, $this));

            $entity = $newSuit->getOriginal();

            $this->persist($entity);

            yield $entity;

            $iterator->next();
        } while (true);
    }
}
