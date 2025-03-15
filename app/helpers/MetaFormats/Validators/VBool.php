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

    public function validateText(mixed $value): bool
    {
        // FILTER_VALIDATE_BOOL is not used because it additionally allows "on", "yes", "off", "no" and ""

        // urlencoded params are strings
        return $value === "0"
            || $value === "1"
            || $value === "true"
            || $value === "false"
            || $value === 0
            || $value === 1
            || $this->validateJson($value);
    }

    public function validateJson(mixed $value): bool
    {
        ///TODO: remove 'false' once the testUpdateInstance test issue is fixed.
        return $value === true || $value === false || $value === 'false';
    }
}
