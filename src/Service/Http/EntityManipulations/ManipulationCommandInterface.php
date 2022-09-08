<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http\EntityManipulations;

use Awwar\SymfonyHttpEntityManager\Service\Http\EntitySuit;

interface ManipulationCommandInterface
{
    public function execute(): void;

    public function getSuit(): EntitySuit;
}
