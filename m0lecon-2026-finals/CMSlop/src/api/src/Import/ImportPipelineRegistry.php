<?php

namespace Herbarium\Import;

class ImportPipelineRegistry implements \ArrayAccess, \Countable, \Iterator
{
    private $ALLOWED_PROCESSORS = [
        \Herbarium\Processors\SanitizationProcessor::class,
        \Herbarium\Processors\NormalizationProcessor::class,
        \Herbarium\Processors\ValidationProcessor::class,
        \Herbarium\Processors\EnrichmentProcessor::class,
        \Herbarium\Processors\DeduplicationProcessor::class,
    ];

    protected $processors;

    protected $config;

    protected $context;

    protected $pipeline;

    public function __construct(array $processors, $config, $context, ImportPipeline $pipeline)
    {
        foreach ($processors as $processor) {
            $this->assertAllowed($processor);
        }
        $this->processors = $processors;
        $this->config     = $config;
        $this->context    = $context;
        $this->pipeline   = $pipeline;
    }

    private function assertAllowed(string $processor): string
    {
        if (!in_array($processor, $this->ALLOWED_PROCESSORS, true)) {
            throw new \InvalidArgumentException(
                "Processor class not allowed: {$processor}"
            );
        }
        return $processor;
    }

    public function count(): int
    {
        return count($this->processors);
    }

    public function run(array $specimen): ?array
    {
        foreach (array_keys($this->processors) as $offset) {
            $processor = $this[$offset];
            $specimen  = $processor->process($specimen);
            if ($specimen === null) {
                return null;
            }
        }
        return $specimen;
    }

    public function getProcessorNames(): array
    {
        return array_keys($this->processors);
    }

    public function runSingle(string $name, array $specimen): ?array
    {
        $processor = $this[$name];
        if ($processor === null) {
            throw new \InvalidArgumentException("Unknown processor: {$name}");
        }
        return $processor->process($specimen);
    }

    public function runBatch(array $specimens): array
    {
        $accepted = [];
        $rejected = 0;

        foreach ($specimens as $specimen) {
            $result = $this->run($specimen);
            if ($result !== null) {
                $accepted[] = $result;
            } else {
                $rejected++;
            }
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }
        $processor  = $this->processors[$offset];
        $this->assertAllowed($processor);
        $components = $this->pipeline->getComponents();
        $processor  = ltrim($components['handlers'][$offset] ?? $processor, '\\');
        $this->assertAllowed($processor);
        return new $processor(
            $this->config,
            $this->context,
            $components
        );
    }

    public function offsetExists($offset): bool
    {
        return isset($this->processors[$offset]);
    }

    public function offsetSet($offset, $value): void
    {
        $this->assertAllowed($value);
        $this->processors[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->processors[$offset]);
    }

    public function current()
    {
        return $this->offsetGet(key($this->processors));
    }

    public function key()
    {
        return key($this->processors);
    }

    public function next(): void
    {
        next($this->processors);
    }

    public function rewind(): void
    {
        reset($this->processors);
    }

    public function valid(): bool
    {
        return key($this->processors) !== null;
    }

    public function has(string $name): bool
    {
        return $this->offsetExists($name);
    }

    public function remove(string $name): void
    {
        $this->offsetUnset($name);
    }

    public function replace(string $name, string $processor): void
    {
        $this->assertAllowed($processor);
        $this->processors[$name] = $processor;
    }
}
