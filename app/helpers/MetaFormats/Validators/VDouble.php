<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class VDouble
{
    public const SWAGGER_TYPE = "number";

    public function validate(mixed $value)
    {
        // check if it is a double
        if (MetaFormatHelper::checkType($value, PhpTypes::Double)) {
            return true;
        }

        // the value may be a string containing the number
        return is_numeric($value);
    }
}
