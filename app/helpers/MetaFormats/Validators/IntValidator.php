<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class IntValidator
{
    public const SWAGGER_TYPE = "integer";

    public function validate(mixed $value)
    {
        ///TODO: check if int
        return true;
    }
}
