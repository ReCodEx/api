<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates integers. Accepts ints as well as their stringified versions.
 */
class VInt extends BaseValidator
{
    public const SWAGGER_TYPE = "integer";

    public function getExampleValue(): string
    {
        return "0";
    }

    public function validateText(mixed $value): bool
    {
        return $this->validateJson($value);
    }

    public function validateJson(mixed $value): bool
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
