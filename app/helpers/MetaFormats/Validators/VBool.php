<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates boolean values. Accepts only boolean true and false.
 */
class VBool extends BaseValidator
{
    public const SWAGGER_TYPE = "boolean";

    public function getExampleValue(): string
    {
        return "true";
    }

    public function validate(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (!$this->strict) {
            // FILTER_VALIDATE_BOOL is not used because it additionally allows "on", "yes", "off", "no" and ""

            if ($value === 0 || $value === 1) {
                return true;
            }

            if (is_string($value)) {
                $lower = strtolower(trim($value));
                return $lower === "0"
                    || $lower === "1"
                    || $lower === "false"
                    || $lower === "true";
            }
        }

        return false;
    }

    public function patchQueryParameter(mixed &$value): void
    {
        if (is_string($value)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        } else {
            $value = (bool)$value;
        }
    }
}
