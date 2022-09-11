<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Exception\NotFoundException;
use Awwar\SymfonyHttpEntityManager\Service\Http\ListIterator\Data;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\FullData;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\NoData;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\Reference;
use Awwar\SymfonyHttpEntityManager\Service\UOW\EntityAtelier;
use Awwar\SymfonyHttpEntityManager\Service\UOW\HttpUnitOfWorkInterface;
use Generator;
use LogicException;

class HttpEntityManager implements HttpEntityManagerInterface, EntityCreatorInterface
{
    public function __construct(
        private HttpUnitOfWorkInterface $unitOfWork,
        private EntityAtelier $entityAtelier
    ) {
    }

    public function find(string $className, mixed $id, array $criteria = []): object
    {
        $suit = $this->entityAtelier->suitUpClass($className);
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

    public function persist(object $object): void
    {
        $suit = $this->entityAtelier->suitUpEntity($object);

        $this->unitOfWork->commit($suit);
    }

    public function flush(): void
    {
        $this->unitOfWork->flush();
    }

    public function remove(object $object): void
    {
        $suit = $this->entityAtelier->suitUpEntity($object);

        $this->unitOfWork->delete($suit);
    }

    public function clear(string $objectName = null): void
    {
        $this->unitOfWork->clear($objectName);
    }

    public function detach(object $object): void
    {
        $suit = $this->entityAtelier->suitUpEntity($object);

        $this->unitOfWork->remove($suit);
    }

    public function refresh(object $object): void
    {
        $suit = $this->entityAtelier->suitUpEntity($object);

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
        $suit = $this->entityAtelier->suitUpClass($className);

        return $suit->getMetadata()->getRepository() ?? new HttpRepository($this, $className);
    }

    public function contains(object $object): bool
    {
        $suit = $this->entityAtelier->suitUpEntity($object);

        return $this->unitOfWork->hasSuit($suit);
    }

    public function merge(object $object): void
    {
        throw new \RuntimeException('Merge is not implemented!');
    }

    public function iterate(
        string $className,
        array $criteria,
        ?string $url = null,
        bool $isFilterOne = false
    ): Generator {
        $suit = $this->entityAtelier->suitUpClass($className);
        $metadata = $suit->getMetadata();

        $criteria = array_merge($isFilterOne ? $metadata->getFilterOneQuery() : $metadata->getFilterQuery(), $criteria);

        $data = $metadata->getClient()->get($url === null ? $metadata->getUrlForList() : $url, $criteria);

        $iterator = $metadata->getListDetermination()($data);

        $nextUrl = null;

        $firstIteration = true;

        do {
            $signal = $iterator->current();

            if ($signal instanceof Data) {
                $nextUrl = $signal->getUrl();
            }

            if ($signal === null) {
                if ($nextUrl === null) {
                    if ($firstIteration === true && $isFilterOne === true) {
                        throw new NotFoundException(entity: $metadata->getName());
                    }
                    break;
                }
                $data = $metadata->getClient()->get($nextUrl, $criteria);

                $iterator = $metadata->getListDetermination()($data);
                $nextUrl = null;
                continue;
            }
            $firstIteration = false;

            $newSuit = $this->entityAtelier->suitUpClass($className);
            $newSuit->callAfterRead($signal->getData(), $this);

            $this->unitOfWork->commit($newSuit);

            yield $newSuit->getOriginal();

            $iterator->next();
        } while (true);
    }

    public function createEntityWithData(string $className, mixed $data): ?object
    {
        $suit = $this->entityAtelier->suitUpClass($className);

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
