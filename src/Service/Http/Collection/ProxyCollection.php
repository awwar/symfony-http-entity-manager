<?php

namespace Awwar\SymfonyHttpEntityManager\Service\Http\Collection;

use Closure;

class ProxyCollection implements Collection
{
    private array $collection = [];

    private bool $isInitialized = false;

    public function __construct(private Closure $initiator)
    {
    }

    public function getIterator(): \Traversable
    {
        $this->tryInit();
        foreach ($this->collection as $item) {
            yield $item;
        }
    }

    public function offsetExists($offset): bool
    {
        $this->tryInit();
        return isset($this->collection[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        $this->tryInit();
        return $this->collection[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->tryInit();
        $this->collection[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        $this->tryInit();
        unset($this->collection[$offset]);
    }

    public function count(): int
    {
        $this->tryInit();
        return count($this->collection);
    }

    private function tryInit(): void
    {
        if ($this->isInitialized === false) {
            $iterator = $this->initiator->call($this);

            foreach ($iterator as $item) {
                $this->collection[] = $item;
            }

            $this->isInitialized = true;
        }
    }
}
