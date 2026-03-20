<?php

namespace Herbarium\Core;

class MiddlewareStack implements \Countable
{
    protected $stack;

    protected $handler;


    public function __construct(array $middleware, $handler)
    {
        $this->stack   = array_map(fn($fn) => [$fn], $middleware);
        $this->handler = $handler;
    }

    public function push(callable $fn): self
    {
        $this->stack[] = [$fn];
        return $this;
    }

    public function resolve()
    {
        if (!($prev = $this->handler)) {
            throw new \LogicException('No handler has been specified');
        }

        foreach (array_reverse($this->stack) as $fn) {
            $prev = $fn[0]($prev);
        }

        return $prev;
    }

    public function setHandler($handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function depth(): int
    {
        return count($this->stack);
    }

    public function prepend(callable $fn): self
    {
        array_unshift($this->stack, [$fn]);
        return $this;
    }

    public function clear(): self
    {
        $this->stack = [];
        return $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    public function count(): int
    {
        return count($this->stack);
    }

    public function toArray(): array
    {
        $out = [];
        foreach ($this->stack as $entry) {
            $out[] = $entry[0];
        }
        return $out;
    }

    public function __wakeup()
    {
        $this->stack = [];
        $this->handler = null;
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
