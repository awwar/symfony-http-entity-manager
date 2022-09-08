<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http\Exception;

use DomainException;
use Throwable;

class InvalidDataException extends DomainException
{
    private array $context = [];

    public function __construct(
        string $entity = "entity",
        ?int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct("Invalid $entity data", (int) $code, $previous);
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
