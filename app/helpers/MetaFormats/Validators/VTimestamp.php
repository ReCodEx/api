<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class VTimestamp
{
    public const SWAGGER_TYPE = "string";

    public function validate(mixed $value): bool
    {
        ///TODO: check if timestamp
        return true;
    }
}
