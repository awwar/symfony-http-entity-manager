<?php

namespace Awwar\SymfonyHttpEntityManager\Service\EntityManipulations;

use Awwar\SymfonyHttpEntityManager\Service\UOW\EntitySuit;

class Delete implements ManipulationCommandInterface
{
    public function __construct(private EntitySuit $suit)
    {
    }

    public function execute(): void
    {
        $metadata = $this->suit->getMetadata();
        $metadata->getClient()->delete($metadata->getUrlForDelete($this->suit->getId()));
    }

    public function getSuit(): EntitySuit
    {
        return $this->suit;
    }
}
