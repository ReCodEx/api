<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class FloatValidator
{
    public const SWAGGER_TYPE = "number";

    public function validate(string $value)
    {
        ///TODO: check if float
        return true;
    }
}
