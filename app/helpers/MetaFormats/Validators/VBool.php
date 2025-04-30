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
            return $value === 0
                || $value === 1
                || $value === "0"
                || $value === "1"
                || $value === "false"
                || $value === "true";
        }

        return false;
    }
}
