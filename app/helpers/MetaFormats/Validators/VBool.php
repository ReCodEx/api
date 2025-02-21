<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class VBool
{
    public const SWAGGER_TYPE = "boolean";

    public function validate(mixed $value)
    {
        // support stringified values as well as 0 and 1
        return MetaFormatHelper::checkType($value, PhpTypes::Bool)
        || $value == 0
        || $value == 1
        || $value == "true"
        || $value == "false";
    }
}
