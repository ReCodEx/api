<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class ArrayValidator
{
    public const SWAGGER_TYPE = "array";

    public function validate(string $value)
    {
        ///TODO: check if array, check content
        return true;
    }
}
