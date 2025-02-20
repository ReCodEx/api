<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\PhpTypes;

/**
 * Placeholder validator used for endpoints with no existing validation rules.
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
