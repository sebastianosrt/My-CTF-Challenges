<?php

namespace Herbarium\Processors;

abstract class AbstractProcessor
{
    protected $config;

    protected $context;

    protected array $components;

    public function __construct($config, $context, array $components)
    {
        $this->config     = $config;
        $this->context    = $context;
        $this->components = $components;
    }

    public static function fromConfig($config, $context, array $components): static
    {
        return new static($config, $context, $components);
    }

    abstract public function process(array $specimen): ?array;

    public function supports(array $specimen): bool
    {
        return true;
    }

    public function getName(): string
    {
        $class = get_class($this);
        $short = substr(strrchr($class, '\\'), 1) ?: $class;
        return $short;
    }

    public function getConfig(): array
    {
        return is_array($this->config) ? $this->config : [];
    }
}
