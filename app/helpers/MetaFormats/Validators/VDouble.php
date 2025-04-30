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

    public function validate(mixed $value): bool
    {
        // check if it is a double or an integer (is_double(0) returns false)
        if (is_double($value) || is_int($value)) {
            return true;
        }

        if ($this->strict) {
            return false;
        }

        // the value may be a string containing the number, or an integer
        return is_numeric($value);
    }
}
