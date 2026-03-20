<?php

namespace Herbarium\Processors;

class NormalizationProcessor extends AbstractProcessor
{
    public function process(array $specimen): ?array
    {
        foreach ($specimen as $key => $value) {
            if (is_string($value)) {
                $specimen[$key] = trim($value);
            }
        }

        if (!empty($specimen['common_name'])) {
            $specimen['common_name'] = ucwords(strtolower($specimen['common_name']));
        }

        if (!empty($specimen['family'])) {
            $specimen['family'] = ucfirst(strtolower($specimen['family']));
        }

        if (!empty($specimen['genus'])) {
            $specimen['genus'] = ucfirst(strtolower($specimen['genus']));
        }

        if (!empty($specimen['collected_date'])) {
            $specimen['collected_date'] = preg_replace('/[\/.]/', '-', $specimen['collected_date']);
        }

        return $specimen;
    }

    public function normalizeField(string $key, string $value): string
    {
        $value = trim($value);

        switch ($key) {
            case 'common_name':
                return ucwords(strtolower($value));
            case 'family':
            case 'genus':
                return ucfirst(strtolower($value));
            case 'collected_date':
                return preg_replace('/[\\/.]/', '-', $value);
            default:
                return $value;
        }
    }

    public function getRules(): array
    {
        return [
            'common_name'    => 'ucwords + strtolower',
            'family'         => 'ucfirst + strtolower',
            'genus'          => 'ucfirst + strtolower',
            'collected_date' => 'replace /. with -',
            '*'              => 'trim',
        ];
    }

    public function needsNormalization(array $specimen): bool
    {
        foreach ($specimen as $key => $value) {
            if (is_string($value) && $value !== $this->normalizeField($key, $value)) {
                return true;
            }
        }
        return false;
    }
}
