<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Accepts everything.
 * Placeholder validator used for endpoints with no existing validation rules.
 * New endpoints should never use this validator, instead use a more restrictive one.
 */
class VMixed
{
    public const SWAGGER_TYPE = "string";

    public function getExampleValue()
    {
        return "value";
    }

    public function validate(mixed $value): bool
    {
        return true;
    }
}
