<?php

namespace Herbarium\Processors;

class SanitizationProcessor extends AbstractProcessor
{
    private static array $maxLengths = [
        'common_name'         => 200,
        'species'             => 200,
        'family'              => 100,
        'genus'               => 100,
        'location_found'      => 300,
        'habitat'             => 300,
        'collected_date'      => 20,
        'collector'           => 200,
        'description'         => 2000,
        'preservation_method' => 200,
    ];

    public function process(array $specimen): ?array
    {
        foreach ($specimen as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            $specimen[$key] = strip_tags($value);

            if ($key === 'description') {
                $specimen[$key] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $specimen[$key]);
            } else {
                $specimen[$key] = preg_replace('/[\x00-\x1F]/', '', $specimen[$key]);
            }

            if (isset(self::$maxLengths[$key]) && mb_strlen($specimen[$key]) > self::$maxLengths[$key]) {
                $specimen[$key] = mb_substr($specimen[$key], 0, self::$maxLengths[$key]);
            }
        }

        return $specimen;
    }

    public function sanitizeField(string $key, string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F]/', '', $value);
        if (isset(self::$maxLengths[$key]) && mb_strlen($value) > self::$maxLengths[$key]) {
            $value = mb_substr($value, 0, self::$maxLengths[$key]);
        }
        return $value;
    }

    public function getLimits(): array
    {
        return self::$maxLengths;
    }

    public function needsSanitization(array $specimen): bool
    {
        foreach ($specimen as $key => $value) {
            if (is_string($value) && $value !== $this->sanitizeField($key, $value)) {
                return true;
            }
        }
        return false;
    }
}
