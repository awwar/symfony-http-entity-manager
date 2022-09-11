<?php

namespace Awwar\SymfonyHttpEntityManager\Exception;

use DomainException;
use Throwable;

class NotFoundException extends DomainException
{
    private array $context = [];

    public function __construct(
        string $entity = "entity",
        ?int $code = 404,
        ?Throwable $previous = null
    ) {
        parent::__construct("$entity not found", (int) $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
