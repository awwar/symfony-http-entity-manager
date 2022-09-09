<?php

namespace Awwar\SymfonyHttpEntityManager\Service\EntityManipulations;

use Awwar\SymfonyHttpEntityManager\Service\UOW\EntitySuit;

class Update implements ManipulationCommandInterface
{
    public function __construct(
        private EntitySuit $suit,
        private array $entityChanges = [],
        private array $relationChanges = [],
    ) {
    }

    public function execute(): void
    {
        $data = $this->suit->callBeforeUpdate(
            $this->entityChanges,
            $this->relationChanges,
            $this->suit->getScalarValues(),
            $this->suit->getRelationValues(),
        );
        $metadata = $this->suit->getMetadata();

        $result = $metadata->getClient()->update($metadata->getUrlForUpdate($this->suit->getId()), $data);

        $this->suit->callAfterUpdate($result);
    }

    public function getSuit(): EntitySuit
    {
        return $this->suit;
    }
}
