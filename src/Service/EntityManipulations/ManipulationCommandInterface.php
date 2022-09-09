<?php

namespace Awwar\SymfonyHttpEntityManager\Service\EntityManipulations;

use Awwar\SymfonyHttpEntityManager\Service\UOW\EntitySuit;

interface ManipulationCommandInterface
{
    public function execute(): void;

    public function getSuit(): EntitySuit;
}
