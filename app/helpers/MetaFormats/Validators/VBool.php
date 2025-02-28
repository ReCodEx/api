<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates boolean values. Accepts only boolean true and false.
 */
class VBool
{
    public const SWAGGER_TYPE = "boolean";

    public function validate(mixed $value)
    {
        return $value === true || $value === false || $value === 'false';
    }
}
