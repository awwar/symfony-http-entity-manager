<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

use Awwar\SymfonyHttpEntityManager\Service\EntityManipulations\ManipulationCommandInterface;

interface HttpUnitOfWorkInterface
{
    public function commit(SuitedUpEntity $suit, bool $withWatch = true): void;

    public function delete(SuitedUpEntity $suit): void;

    public function remove(SuitedUpEntity $suit): void;

    public function upgrade(SuitedUpEntity $suit): void;

    public function clear(string $objectName = null): void;

    public function getFromIdentity(SuitedUpEntity $suit): SuitedUpEntity;

    public function hasSuit(SuitedUpEntity $suit): bool;

    public function flush(): void;
}
