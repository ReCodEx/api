<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\PhpTypes;

/**
 * Validates integers. Accepts ints as well as their stringified versions.
 */
class VInt
{
    public const SWAGGER_TYPE = "integer";

    public function getExampleValue()
    {
        return "0";
    }

    public function validate(mixed $value)
    {
        // check if it is an integer (does not handle integer strings)
        if (is_int($value)) {
            return true;
        }

        // the value may be a string containing the integer
        if (!is_numeric($value)) {
            return false;
        }

        // if it is a numeric string, check if it is an integer or float
        return intval($value) == floatval($value);
    }
}
