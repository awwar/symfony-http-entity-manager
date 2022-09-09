<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations\Create;
use Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations\Delete;
use Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations\Update;
use Exception;

class HttpUnitOfWork implements HttpUnitOfWorkInterface
{
    /**
     * @var EntitySuit[]
     */
    private array $identityMap = [];

    private array $keyToSplIdMap = [];

    public function commit(EntitySuit $suit, bool $withWatch = true): void
    {
        if ($this->hasSuit($suit)) {
            return;
        }

        if ($withWatch) {
            $suit->startWatch();
        }

        $splId = $suit->getSPLId();

        $this->identityMap[$splId] = $suit;

        if (false === $suit->isNew()) {
            $this->keyToSplIdMap[$suit->getUniqueId()] = $splId;
        }
    }

    public function upgrade(EntitySuit $suit): void
    {
        if ($suit->isNew()) {
            throw new Exception("Unable to upgrade new entity");
        }

        if (false === isset($this->identityMap[$suit->getSPLId()])) {
            throw new Exception(sprintf("Unable to find %s by id: %s", $suit->getClass(), $suit->getId()));
        }

        if ($suit->isDeleted()) {
            $this->remove($suit);
        } else {
            $suit->startWatch();
            $this->identityMap[$suit->getSPLId()] = $suit;
            $this->keyToSplIdMap[$suit->getUniqueId()] = $suit->getSPLId();
        }
    }

    public function delete(EntitySuit $suit): void
    {
        if (false === $this->hasSuit($suit)) {
            return;
        }

        $suit->delete();

        $newSuit = $this->getFromIdentity($suit);

        $newSuit->delete();
    }

    public function remove(EntitySuit $suit): void
    {
        if (false === $this->hasSuit($suit)) {
            return;
        }

        $newSuit = $this->getFromIdentity($suit);

        if (false === $newSuit->isNew()) {
            unset($this->keyToSplIdMap[$newSuit->getUniqueId()]);
        }

        unset($this->identityMap[$newSuit->getSPLId()]);
    }

    public function clear(string $objectName = null): void
    {
        if ($objectName === null) {
            $this->identityMap = [];
            $this->keyToSplIdMap = [];
        } else {
            foreach ($this->identityMap as $suit) {
                if ($suit->getClass() === $objectName) {
                    $this->remove($suit);
                }
            }
        }
    }

    public function flush(): void
    {
        $forCreate = [];
        $forDelete = [];
        $forUpdate = [];

        foreach ($this->identityMap as $splId => $suit) {
            if ($suit->isDeleted()) {
                $forDelete[$splId] = new Delete($suit);
            } elseif ($suit->isNew()) {
                $forCreate[$splId] = new Create($suit);
            } else {
                if (false === $suit->isProxyInitialized()) {
                    continue;
                }

                $entityChanges = $suit->getEntityChanges();
                $relationChanges = $suit->getRelationChanges();

                if ($suit->isChanged($entityChanges, $relationChanges) === false) {
                    continue;
                }

                foreach ($relationChanges as $relations) {
                    foreach ($relations as $entity) {
                        if (isset($this->identityMap[spl_object_id($entity)])) {
                            continue;
                        }

                        throw new \RuntimeException(sprintf("Found unpersisted entity %s", get_class($entity)));
                    }
                }

                $forUpdate[$splId] = new Update($suit, $entityChanges, $relationChanges);
            }
        }

        $manipulations = array_merge($forCreate, $forUpdate, $forDelete);

        foreach ($manipulations as $manipulation) {
            $manipulation->execute();

            $suit = $manipulation->getSuit();

            $this->upgrade($suit);
        }
    }

    public function getFromIdentity(EntitySuit $suit): EntitySuit
    {
        if (false === $this->hasSuit($suit)) {
            throw new Exception(sprintf("Unable to find %s by id: %s", $suit->getClass(), $suit->getId()));
        }

        $splId = $suit->isNew() ? $suit->getSPLId() : $this->keyToSplIdMap[$suit->getUniqueId()];

        return $this->identityMap[$splId];
    }

    public function hasSuit(EntitySuit $suit): bool
    {
        return $suit->isNew()
            ? isset($this->identityMap[$suit->getSPLId()])
            : isset($this->keyToSplIdMap[$suit->getUniqueId()]);
    }
}
