<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class EmailValidator extends StringValidator
{
    public function __construct()
    {
        parent::__construct(1);
    }

    public function validate(string $value)
    {
        if (!parent::validate($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
