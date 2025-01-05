<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class UuidValidator extends StringValidator
{
    public function __construct()
    {
        parent::__construct(regex: "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/");
    }

    public function validate(string $value)
    {
        return parent::validate($value);
    }
}
