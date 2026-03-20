<?php

namespace Herbarium\Processors;

class EnrichmentProcessor extends AbstractProcessor
{
    public function process(array $specimen): ?array
    {
        if (empty($specimen['genus']) && !empty($specimen['species'])) {
            $parts = explode(' ', $specimen['species'], 2);
            if (count($parts) >= 1) {
                $specimen['genus'] = ucfirst(strtolower($parts[0]));
            }
        }

        if (empty($specimen['collector'])) {
            $specimen['collector'] = $this->components['default_collector'] ?? 'Unknown';
        }

        if (empty($specimen['habitat']) && !empty($this->config['default_habitat'])) {
            $specimen['habitat'] = $this->config['default_habitat'];
        }

        if (empty($specimen['collected_date'])) {
            $specimen['collected_date'] = date('Y-m-d');
        }

        return $specimen;
    }

    public function inferGenus(string $species): ?string
    {
        $parts = explode(' ', $species, 2);
        if (count($parts) >= 1 && strlen($parts[0]) > 0) {
            return ucfirst(strtolower($parts[0]));
        }
        return null;
    }

    public function getDefaults(): array
    {
        return [
            'collector'      => $this->components['default_collector'] ?? 'Unknown',
            'habitat'        => $this->config['default_habitat'] ?? null,
            'collected_date' => date('Y-m-d'),
        ];
    }

    public function getMissingFields(array $specimen): array
    {
        $missing = [];

        if (empty($specimen['genus']) && !empty($specimen['species'])) {
            $missing[] = 'genus';
        }
        if (empty($specimen['collector'])) {
            $missing[] = 'collector';
        }
        if (empty($specimen['habitat']) && !empty($this->config['default_habitat'])) {
            $missing[] = 'habitat';
        }
        if (empty($specimen['collected_date'])) {
            $missing[] = 'collected_date';
        }

        return $missing;
    }
}
