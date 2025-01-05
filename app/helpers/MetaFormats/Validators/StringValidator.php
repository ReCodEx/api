<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class StringValidator
{
    public const SWAGGER_TYPE = "string";
    private int $minLength;
    private int $maxLength;
    private ?string $regex;

    public function __construct(int $minLength = 0, int $maxLength = -1, ?string $regex = null)
    {
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->regex = $regex;
    }

    public function validate(string $value)
    {
        if (!PrimitiveFormatValidators::checkType($value, PhpTypes::String)) {
            return false;
        }

        $length = strlen($value);
        if ($length < $this->minLength) {
            return false;
        }
        if ($this->maxLength !== -1 && $length > $this->maxLength) {
            return false;
        }

        if ($this->regex === null) {
            return true;
        }

        return preg_match($this->regex, $value) === 1;
    }
}
