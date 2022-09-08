<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\Annotation\RelationMap;
use Awwar\SymfonyHttpEntityManager\Service\Http\Collection\GeneralCollection;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\FullData;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\NoData;
use Awwar\SymfonyHttpEntityManager\Service\Http\Resource\Reference;
use LogicException;

class RelationMapper implements RelationMapperInterface
{
    public function __construct(
        private HttpUnitOfWorkInterface $unitOfWork,
        private HttpEntityManagerInterface $em
    ) {
    }

    public function map(iterable $data, array $setting): ?object
    {
        $isOne = $setting['expects'] === RelationMap::ONE;
        $entityClass = $setting['class'];
        //$lateUrl = $setting['lateUrl'];

        $result = $isOne ? null : [];

        foreach ($data as $datum) {
            if ($datum instanceof NoData) {
                //ToDo: фича по позднему проксированию пока в разработке
                //if (empty($lateUrl)) {
                //    throw new \RuntimeException(
                //        sprintf("Late url must be configured, when %s load without data", $entityClass)
                //    );
                //}
                //$fullUrl = str_replace('{id}', $parentId, $lateUrl);
                //$mapper = $this;
                //
                //if ($isOne) {
                //    try {
                //        $data = $suit->getMetadata()->getClient()->get($fullUrl, []);
                //        $suit->callAfterRead($data, $mapper);
                //        $this->unitOfWork->commit($suit);
                //        return $this->unitOfWork->getFromIdentityBySuit($suit)->getOriginal();
                //    } catch (NotFoundException) {
                //        return null;
                //    }
                //} else {
                //    $em = $this->em;
                //    return new ProxyCollection(function () use ($suit, $fullUrl, $em) {
                //        return $em->iterate($suit->getClass(), [], $fullUrl);
                //    });
                //}

                break;
            }

            $suit = $this->unitOfWork->newEntity($entityClass);

            $entity = $this->getEntity($datum, $suit);

            if ($isOne) {
                $result = $entity;
                break;
            }

            $result[] = $entity;
        }

        return $isOne ? $result : new GeneralCollection($result);
    }

    private function getEntity(mixed $data, EntitySuit $suit): object
    {
        if ($data instanceof FullData) {
            $suit->setIdAfterRead($data->getData());
        } elseif ($data instanceof Reference) {
            $suit->proxy(fn ($obj) => $this->em->refresh($obj), $data->getId());
        } else {
            throw new LogicException("Unable to map relation - invalid data type!");
        }

        if (false === $this->unitOfWork->hasEntity($suit)) {
            $this->unitOfWork->commit($suit, false);

            if ($data instanceof FullData) {
                $suit->callAfterRead($data->getData(), $this);
            }

            $this->unitOfWork->upgrade($suit);
        }

        return $this->unitOfWork->getFromIdentityBySuit($suit)->getOriginal();
    }
}
