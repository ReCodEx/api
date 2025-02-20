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
        if (!MetaFormatHelper::checkType($value, PhpTypes::Int)) {
            throw new InternalServerException("err: {$value}");
        }
        return MetaFormatHelper::checkType($value, PhpTypes::Int);
    }
}
