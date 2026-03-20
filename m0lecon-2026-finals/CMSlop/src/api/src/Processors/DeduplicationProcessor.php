<?php

namespace Herbarium\Processors;

use Herbarium\Core\Database;

class DeduplicationProcessor extends AbstractProcessor
{
    public function process(array $specimen): ?array
    {
        if (!empty($this->components['allow_duplicates'])) {
            return $specimen;
        }

        if (empty($specimen['species']) || empty($specimen['location_found'])) {
            return $specimen;
        }

        $existing = Database::prepared(
            "SELECT id FROM specimens WHERE species = ? AND location_found = ? LIMIT 1",
            [$specimen['species'], $specimen['location_found']]
        );

        if (!empty($existing)) {
            return null;
        }

        return $specimen;
    }

    public function isDuplicate(string $species, string $location): bool
    {
        $existing = Database::prepared(
            "SELECT id FROM specimens WHERE species = ? AND location_found = ? LIMIT 1",
            [$species, $location]
        );

        return !empty($existing);
    }

    public function getExisting(string $species): array
    {
        return Database::prepared(
            "SELECT id, species, location_found, imported_at FROM specimens WHERE species = ? ORDER BY imported_at DESC",
            [$species]
        );
    }

    public function countDuplicates(array $specimens): int
    {
        $count = 0;
        foreach ($specimens as $s) {
            if (!empty($s['species']) && !empty($s['location_found'])) {
                if ($this->isDuplicate($s['species'], $s['location_found'])) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
