<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates doubles. Accepts doubles as well as their stringified versions.
 */
class VDouble
{
    public const SWAGGER_TYPE = "number";

    public function validate(mixed $value)
    {
        // check if it is a double
        if (is_double($value)) {
            return true;
        }

        // the value may be a string containing the number, or an integer
        return is_numeric($value);
    }
}
