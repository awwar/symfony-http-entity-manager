<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations;

use Awwar\SymfonyHttpEntityManager\Service\Http\EntitySuit;

class Create implements ManipulationCommandInterface
{
    public function __construct(private EntitySuit $suit)
    {
    }

    public function execute(): void
    {
        $data = $this->suit->callBeforeCreate($this->suit->getScalarValues(), $this->suit->getRelationValues());
        $metadata = $this->suit->getMetadata();

        $response = $metadata->getClient()->create($metadata->getUrlForCreate(), $data);

        $this->suit->callAfterCreate($response);
    }

    public function getSuit(): EntitySuit
    {
        return $this->suit;
    }
}
