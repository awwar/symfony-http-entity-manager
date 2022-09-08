<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http;

use Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations\ManipulationCommandInterface;

interface HttpUnitOfWorkInterface
{
    public function commit(EntitySuit $suit, bool $withWatch = true): void;

    public function delete(EntitySuit $suit): void;

    public function remove(EntitySuit $suit): void;

    public function upgrade(EntitySuit $suit): void;

    public function clear(string $objectName = null): void;

    public function getFromIdentity(string $id, string $entityClass): EntitySuit;

    public function newEntity(string $entityClass): EntitySuit;

    public function hasEntity(EntitySuit $suit): bool;

    public function getFromIdentityBySuit(EntitySuit $suit): EntitySuit;

    public function isSuitExist(EntitySuit $suit): bool;

    /**
     * @return ManipulationCommandInterface[]
     */
    public function getChanges(): array;
}
