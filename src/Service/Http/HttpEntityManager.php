<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Exception\NotFoundException;
use Awwar\SymfonyHttpEntityManager\Service\Http\ListIterator\Data;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\FullData;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\NoData;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\Reference;
use Awwar\SymfonyHttpEntityManager\Service\UOW\EntitySuitFactory;
use Awwar\SymfonyHttpEntityManager\Service\UOW\HttpUnitOfWorkInterface;
use Awwar\SymfonyHttpEntityManager\Service\UOW\MetadataRegistryInterface;
use Generator;
use LogicException;

class HttpEntityManager implements HttpEntityManagerInterface, EntityCreatorInterface
{
    public function __construct(
        private HttpUnitOfWorkInterface $unitOfWork,
        private MetadataRegistryInterface $metadataRegistry,
        private EntitySuitFactory $entitySuitFactory
    ) {
    }

    public function find(string $className, mixed $id, array $criteria = []): object
    {
        $suit = $this->entitySuitFactory->createFromClass($className);
        $suit->setId($id);

        if (false === $this->unitOfWork->hasSuit($suit)) {
            $metadata = $suit->getMetadata();

            $newCriteria = array_merge($metadata->getGetOneQuery(), $criteria);

            $data = $metadata->getClient()->get($metadata->getUrlForOne($id), $newCriteria);

            $suit->callAfterRead($data, $this);

            $this->unitOfWork->commit($suit);
        }

        return $this->unitOfWork->getFromIdentity($suit)->getOriginal();
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
        $this->unitOfWork->flush();
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

        $suit = $this->unitOfWork->getFromIdentity($suit);
        $metadata = $suit->getMetadata();

        $data = $metadata->getClient()->get(
            $metadata->getUrlForOne($suit->getId()),
            $metadata->getGetOneQuery()
        );

        $suit->callAfterRead($data, $this);
    }

    public function getRepository(string $className): HttpRepositoryInterface
    {
        return $this->metadataRegistry->get($className)->getRepository() ?? new HttpRepository($this, $className);
    }

    public function contains(object $object): bool
    {
        $suit = $this->entitySuitFactory->createDirty($object);

        return $this->unitOfWork->hasSuit($suit);
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

            $newSuit = $this->entitySuitFactory->createFromClass($className);
            $newSuit->callAfterRead($signal->getData(), $this);

            $this->unitOfWork->commit($newSuit);

            yield $newSuit->getOriginal();

            $iterator->next();
        } while (true);
    }

    public function createEntityWithData(string $className, mixed $data): ?object
    {
        $suit = $this->entitySuitFactory->createFromClass($className);

        if ($data instanceof FullData) {
            $suit->setIdAfterRead($data->getData());
        } elseif ($data instanceof Reference) {
            $suit->proxy(fn ($obj) => $this->refresh($obj), $data->getId());
        } elseif ($data instanceof NoData) {
            return null;
        } else {
            throw new LogicException("Unable to map relation - invalid data type!");
        }

        if (false === $this->unitOfWork->hasSuit($suit)) {
            $this->unitOfWork->commit($suit, false);

            if ($data instanceof FullData) {
                $suit->callAfterRead($data->getData(), $this);
            }

            $this->unitOfWork->upgrade($suit);
        }

        return $this->unitOfWork->getFromIdentity($suit)->getOriginal();
    }
}
