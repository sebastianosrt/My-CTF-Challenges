<?php

namespace Herbarium\Processors;

class ValidationProcessor extends AbstractProcessor
{
    public function process(array $specimen): ?array
    {
        $required = $this->components['required_fields'] ?? ['common_name'];

        foreach ($required as $field) {
            if (empty($specimen[$field])) {
                return null;
            }
        }

        if (!empty($specimen['species']) && mb_strlen($specimen['species']) < 3) {
            return null;
        }

        return $specimen;
    }

    public function getRequiredFields(): array
    {
        return $this->components['required_fields'] ?? ['common_name'];
    }

    public function validate(array $specimen): array
    {
        $errors   = [];
        $required = $this->getRequiredFields();

        foreach ($required as $field) {
            if (empty($specimen[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        if (!empty($specimen['species']) && mb_strlen($specimen['species']) < 3) {
            $errors[] = 'Species name is suspiciously short (< 3 chars)';
        }

        return $errors;
    }

    public function isValid(array $specimen): bool
    {
        return empty($this->validate($specimen));
    }
}
