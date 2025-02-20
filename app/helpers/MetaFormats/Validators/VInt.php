<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class VInt
{
    public const SWAGGER_TYPE = "integer";

    public function validate(mixed $value)
    {
        // throw new InternalServerException("integer:" . gettype($value));
        ///TODO: check if int
        return true;
    }
}
