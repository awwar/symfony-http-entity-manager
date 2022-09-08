<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Exception;
use Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations\Create;
use Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations\Delete;
use Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations\Update;

class HttpUnitOfWork implements HttpUnitOfWorkInterface
{
    /**
     * @var EntitySuit[]
     */
    private array $identityMap = [];

    private array $keyToSplMap = [];

    public function __construct(private EntitySuitFactory $suitFactory)
    {
    }

    public function newEntity(string $entityClass): EntitySuit
    {
        return $this->suitFactory->createFromClass($entityClass);
    }

    public function commit(EntitySuit $suit, bool $withWatch = true): void
    {
        if ($this->isSuitExist($suit)) {
            return;
        }

        if ($withWatch) {
            $suit->startWatch();
        }

        $spl = $suit->getSPL();

        $this->identityMap[$spl] = $suit;

        if (false === $suit->isNew()) {
            $this->keyToSplMap[$suit->getUniqueId()] = $spl;
        }
    }

    public function upgrade(EntitySuit $suit): void
    {
        if ($suit->isNew()) {
            throw new Exception("Unable to upgrade new entity");
        }

        if (false === isset($this->identityMap[$suit->getSPL()])) {
            throw new Exception(sprintf("Unable to find %s by id: %s", $suit->getClass(), $suit->getId()));
        }

        if ($suit->isDeleted()) {
            $this->remove($suit);
        } else {
            $suit->startWatch();
            $this->identityMap[$suit->getSPL()] = $suit;
            $this->keyToSplMap[$suit->getUniqueId()] = $suit->getSPL();
        }
    }

    public function delete(EntitySuit $suit): void
    {
        if (false === $this->isSuitExist($suit)) {
            return;
        }

        $suit->delete();

        $newSuit = $this->getFromIdentityBySuit($suit);

        $newSuit->delete();
    }

    public function remove(EntitySuit $suit): void
    {
        if (false === $this->isSuitExist($suit)) {
            return;
        }

        $newSuit = $this->getFromIdentityBySuit($suit);

        if (false === $newSuit->isNew()) {
            unset($this->keyToSplMap[$newSuit->getUniqueId()]);
        }

        unset($this->identityMap[$newSuit->getSPL()]);
    }

    public function clear(string $objectName = null): void
    {
        if ($objectName === null) {
            $this->identityMap = [];
            $this->keyToSplMap = [];
        } else {
            foreach ($this->identityMap as $suit) {
                if (get_class($suit->getOriginal()) === $objectName) {
                    $this->remove($suit);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getChanges(): array
    {
        $forCreate = [];
        $forDelete = [];
        $forUpdate = [];

        foreach ($this->identityMap as $suit) {
            if ($suit->isDeleted()) {
                $forDelete[] = new Delete($suit);
            } elseif ($suit->isNew()) {
                $forCreate[] = new Create($suit);
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
                        $suit = $this->suitFactory->create($entity);

                        if ($this->isSuitExist($suit)) {
                            continue;
                        }

                        $this->commit($suit);

                        $forCreate[] = new Create($suit);
                    }
                }

                $forUpdate[] = new Update($suit, $entityChanges, $relationChanges);
            }
        }

        return array_merge($forCreate, $forUpdate, $forDelete);
    }

    public function getFromIdentity(string $id, string $entityClass): EntitySuit
    {
        $key = sha1($id . $entityClass);
        $spl = $this->keyToSplMap[$key] ?? throw new Exception("Unable to find $entityClass by id: $id");

        return $this->identityMap[$spl];
    }

    public function hasEntity(EntitySuit $suit): bool
    {
        return $this->isSuitExist($suit);
    }

    public function getFromIdentityBySuit(EntitySuit $suit): EntitySuit
    {
        if (false === $this->isSuitExist($suit)) {
            throw new Exception(sprintf("Unable to find %s by id: %s", $suit->getClass(), $suit->getId()));
        }

        $spl = $suit->isNew() ? $suit->getSPL() : $this->keyToSplMap[$suit->getUniqueId()];

        return $this->identityMap[$spl];
    }

    public function isSuitExist(EntitySuit $suit): bool
    {
        return $suit->isNew()
            ? isset($this->identityMap[$suit->getSPL()])
            : isset($this->keyToSplMap[$suit->getUniqueId()]);
    }
}
