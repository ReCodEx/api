<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class BoolValidator
{
    public const SWAGGER_TYPE = "boolean";

    public function validate(string $value)
    {
        ///TODO: check if bool
        return true;
    }
}
