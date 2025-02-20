<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class VDouble
{
    public const SWAGGER_TYPE = "number";

    public function validate(mixed $value)
    {
        ///TODO: check if float
        return true;
    }
}
