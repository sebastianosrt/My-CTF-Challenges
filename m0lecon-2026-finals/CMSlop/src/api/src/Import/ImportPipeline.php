<?php

namespace Herbarium\Import;

class ImportPipeline
{
    private array $components = [];
    private array $results = [];

    public function __construct(array $components = [])
    {
        $this->components = $components;
    }

    public function getComponents(): array
    {
        return $this->components;
    }

    public function addComponent(string $name, $value): void
    {
        $this->components[$name] = $value;
    }

    public function getComponent(string $name)
    {
        return $this->components[$name] ?? null;
    }

    public function hasComponent(string $name): bool
    {
        return array_key_exists($name, $this->components);
    }

    public function removeComponent(string $name): void
    {
        unset($this->components[$name]);
    }

    public function merge(array $components): void
    {
        $this->components = array_merge($this->components, $components);
    }

    public function toArray(): array
    {
        return $this->components;
    }

    public function reset(): void
    {
        $this->components = [];
    }

    public function snapshot(): array
    {
        return [
            'components' => $this->components,
            'timestamp'  => date('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function __invoke()
    {
        $this->results = [];
        foreach ($this->components as $step) {
            if (isset($step['result'])) {
                $this->results[] = $step['result'];
            } elseif (is_callable($step)) {
                $this->results[] = $step();
            } else {
                $this->results[] = $step;
            }
        }
        return $this->results;
    }

    public function keys(): array
    {
        return array_keys($this->components);
    }
}
