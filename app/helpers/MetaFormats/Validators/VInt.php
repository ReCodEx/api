<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class VInt
{
    public const SWAGGER_TYPE = "integer";

    public function getExampleValue()
    {
        return "0";
    }

    public function validate(mixed $value)
    {
        // check if it is an integer
        if (MetaFormatHelper::checkType($value, PhpTypes::Int)) {
            return true;
        }

        // the value may be a string containing the integer
        if (!is_numeric($value)) {
            return false;
        }

        return intval($value) == floatval($value);
    }
}
