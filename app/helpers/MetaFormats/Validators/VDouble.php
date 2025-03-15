<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates doubles. Accepts doubles as well as their stringified versions.
 */
class VDouble extends BaseValidator
{
    public const SWAGGER_TYPE = "number";

    public function getExampleValue(): string
    {
        return "0.1";
    }

    public function validateText(mixed $value): bool
    {
        return $this->validateJson($value);
    }

    public function validateJson(mixed $value): bool
    {
        // check if it is a double
        if (is_double($value)) {
            return true;
        }

        // the value may be a string containing the number, or an integer
        return is_numeric($value);
    }
}
