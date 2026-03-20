<?php

namespace Herbarium\Core;

class ResourceHandle
{
    protected $methods = [];

    public function __construct(array $methods)
    {
        $this->methods = $methods;

        foreach ($methods as $name => $fn) {
            $this->{'_fn_' . $name} = $fn;
        }
    }

    public function __destruct()
    {
        if (isset($this->_fn_close)) {
            ($this->_fn_close)();
        }
    }

    public function has(string $name): bool
    {
        return isset($this->{'_fn_' . $name});
    }

    public function replace(string $name, callable $fn): void
    {
        $this->methods[$name]       = $fn;
        $this->{'_fn_' . $name} = $fn;
    }

    public function remove(string $name): void
    {
        unset($this->methods[$name]);
        unset($this->{'_fn_' . $name});
    }

    public function getMethodNames(): array
    {
        return array_keys($this->methods);
    }

    public function invoke(string $name, ...$args)
    {
        $prop = '_fn_' . $name;
        if (!isset($this->$prop)) {
            throw new \BadMethodCallException("No handler registered for '{$name}'");
        }
        return ($this->$prop)(...$args);
    }

    public function duplicate(): self
    {
        return new self($this->methods);
    }

    public function count(): int
    {
        return count($this->methods);
    }

    public function __wakeup()
    {
        unset($this->_fn_close);
        $this->methods = [];
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
