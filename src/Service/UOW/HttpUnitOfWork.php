<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

use Awwar\SymfonyHttpEntityManager\Exception\IdentityNotFoundException;
use Awwar\SymfonyHttpEntityManager\Service\EntityManipulations\Create;
use Awwar\SymfonyHttpEntityManager\Service\EntityManipulations\Delete;
use Awwar\SymfonyHttpEntityManager\Service\EntityManipulations\Update;
use Exception;

class HttpUnitOfWork implements HttpUnitOfWorkInterface
{
    /**
     * @var SuitedUpEntity[]
     */
    private array $identityMap = [];

    private array $keyToSplIdMap = [];

    public function commit(SuitedUpEntity $suit, bool $withWatch = true): void
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

    public function upgrade(SuitedUpEntity $suit): void
    {
        if ($suit->isNew()) {
            throw new Exception("Unable to upgrade new entity");
        }

        if (false === isset($this->identityMap[$suit->getSPLId()])) {
            throw IdentityNotFoundException::create($suit->getClass(), $suit->getId());
        }

        if ($suit->isDeleted()) {
            $this->remove($suit);
            unset($this->identityMap[$suit->getSPLId()]);
            unset($this->keyToSplIdMap[$suit->getUniqueId()]);
        } else {
            $suit->startWatch();
            $this->identityMap[$suit->getSPLId()] = $suit;
            $this->keyToSplIdMap[$suit->getUniqueId()] = $suit->getSPLId();
        }
    }

    public function delete(SuitedUpEntity $suit): void
    {
        if (false === $this->hasSuit($suit)) {
            return;
        }

        $suit->delete();

        $newSuit = $this->getFromIdentity($suit);

        $newSuit->delete();
    }

    public function remove(SuitedUpEntity $suit): void
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

                $scalarChanges = $suit->getScalarChanges();
                $relationChanges = $suit->getRelationChanges();

                if (empty($scalarChanges) && empty($relationChanges)) {
                    continue;
                }

                foreach ($relationChanges as $relations) {
                    foreach ($relations as $entity) {
                        if (isset($this->identityMap[spl_object_id($entity)])) {
                            continue;
                        }

                        throw IdentityNotFoundException::createFromClassName((string) get_class($entity));
                    }
                }

                $forUpdate[$splId] = new Update($suit, $scalarChanges, $relationChanges);
            }
        }

        $manipulations = array_merge($forCreate, $forUpdate, $forDelete);

        foreach ($manipulations as $manipulation) {
            $manipulation->execute();

            $suit = $manipulation->getSuit();

            $this->upgrade($suit);
        }
    }

    public function getFromIdentity(SuitedUpEntity $suit): SuitedUpEntity
    {
        if (false === $this->hasSuit($suit)) {
            throw IdentityNotFoundException::create($suit->getClass(), $suit->getId());
        }

        $splId = $suit->isNew() ? $suit->getSPLId() : $this->keyToSplIdMap[$suit->getUniqueId()];

        return $this->identityMap[$splId];
    }

    public function hasSuit(SuitedUpEntity $suit): bool
    {
        return $suit->isNew()
            ? isset($this->identityMap[$suit->getSPLId()])
            : isset($this->keyToSplIdMap[$suit->getUniqueId()]);
    }
}
