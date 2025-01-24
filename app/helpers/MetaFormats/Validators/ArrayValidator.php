<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

class ArrayValidator
{
    public const SWAGGER_TYPE = "array";

    // validator used for elements
    private mixed $nestedValidator;

    public function __construct(mixed $nestedValidator = null)
    {
        $this->nestedValidator = $nestedValidator;
    }

    public function getExampleValue()
    {
        if ($this->nestedValidator !== null && method_exists(get_class($this->nestedValidator), "getExampleValue")) {
            return $this->nestedValidator->getExampleValue();
        }

        return null;
    }

    public function getElementSwaggerType()
    {
        // default to string for unknown element types
        if ($this->nestedValidator === null) {
            return null;
        }

        return $this->nestedValidator::SWAGGER_TYPE;
    }

    public function validate(mixed $value)
    {
        ///TODO: check if array, check content
        return true;
    }
}
