<?php

namespace Awwar\SymfonyHttpEntityManager\Service\EntityManipulations;

use Awwar\SymfonyHttpEntityManager\Service\UOW\SuitedUpEntity;

interface ManipulationCommandInterface
{
    public function execute(): void;

    public function getSuit(): SuitedUpEntity;
}
