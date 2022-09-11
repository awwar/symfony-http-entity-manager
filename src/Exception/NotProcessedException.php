<?php

namespace Awwar\SymfonyHttpEntityManager\Exception;

use DomainException;
use Throwable;

class NotProcessedException extends DomainException
{
    private array $context = [];

    public function __construct(
        string $entity = "entity",
        ?int $code = 500,
        ?Throwable $previous = null
    ) {
        parent::__construct("Had some error while $entity procession", (int) $code, $previous);
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
