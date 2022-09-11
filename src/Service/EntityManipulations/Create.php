<?php

namespace Awwar\SymfonyHttpEntityManager\Service\EntityManipulations;

use Awwar\SymfonyHttpEntityManager\Service\UOW\SuitedUpEntity;

class Create implements ManipulationCommandInterface
{
    public function __construct(private SuitedUpEntity $suit)
    {
    }

    public function execute(): void
    {
        $data = $this->suit->callBeforeCreate($this->suit->getScalarSnapshot(), $this->suit->getRelationValues());
        $metadata = $this->suit->getMetadata();

        $response = $metadata->getClient()->create($metadata->getUrlForCreate(), $data);

        $this->suit->callAfterCreate($response);
    }

    public function getSuit(): SuitedUpEntity
    {
        return $this->suit;
    }
}
