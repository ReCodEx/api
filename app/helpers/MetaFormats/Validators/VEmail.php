<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class VEmail extends VString
{
    public function __construct()
    {
        parent::__construct(1);
    }

    public function getExampleValue()
    {
        return "name@domain.tld";
    }

    public function validate(mixed $value): bool
    {
        if (!parent::validate($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) != false;
    }
}
