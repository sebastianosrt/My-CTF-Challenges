<?php

namespace Herbarium\Specimens;

use Herbarium\Core\Database;

class SpecimenCollector
{
    protected $specimens;

    protected int $importedBy;

    protected string $source;

    public function __construct(iterable $specimens, int $importedBy, string $source)
    {
        $this->specimens  = $specimens;
        $this->importedBy = $importedBy;
        $this->source     = $source;
    }

    public function flush(?callable $callback = null): int
    {
        $count = 0;

        foreach ($this->specimens as $specimen) {
            if ($callback !== null) {
                $specimen = $callback($specimen);
                if ($specimen === null) {
                    continue;
                }
            }

            Database::preparedExec(
                "INSERT INTO specimens (common_name, species, family, genus, location_found, habitat, collected_date, collector, description, preservation_method, imported_by, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    (string)($specimen['common_name'] ?? ''),
                    (string) ($specimen['species'] ?? ''),
                    (string) ($specimen['family'] ?? ''),
                    (string) ($specimen['genus'] ?? ''),
                    (string) ($specimen['location_found'] ?? ''),
                    (string) ($specimen['habitat'] ?? ''),
                    (string) ($specimen['collected_date'] ?? ''),
                    (string) ($specimen['collector'] ?? ''),
                    (string) ($specimen['description'] ?? ''),
                    (string) ($specimen['preservation_method'] ?? ''),
                    $this->importedBy,
                    $this->source,
                ]
            );
            $count++;
        }

        $this->specimens = [];
        return $count;
    }

    public function add(array $specimen): void
    {
        if (is_array($this->specimens)) {
            $this->specimens[] = $specimen;
        }
    }

    public function count(): int
    {
        return is_array($this->specimens) ? count($this->specimens) : 0;
    }

    public function filter(callable $fn): int
    {
        if (!is_array($this->specimens)) {
            return 0;
        }
        $before = count($this->specimens);
        $this->specimens = array_values(array_filter($this->specimens, $fn));
        return $before - count($this->specimens);
    }

    public function toArray(): array
    {
        return is_array($this->specimens) ? $this->specimens : [];
    }

    public function __destruct()
    {
        $this->flush();
    }
}
