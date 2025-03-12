<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates arrays and their nested elements.
 */
class VArray extends BaseValidator
{
    public const SWAGGER_TYPE = "array";

    // validator used for elements
    private mixed $nestedValidator;

    /**
     * Creates an array validator.
     * @param mixed $nestedValidator A validator that will be applied on all elements
     *  (validator arrays are not supported).
     */
    public function __construct(mixed $nestedValidator = null)
    {
        $this->nestedValidator = $nestedValidator;
    }

    public function getExampleValue()
    {
        if ($this->nestedValidator !== null) {
            return $this->nestedValidator->getExampleValue();
        }

        return null;
    }

    /**
     * @return string|null Returns the element swagger type. Can be null if the element validator is not set.
     */
    public function getElementSwaggerType(): mixed
    {
        if ($this->nestedValidator === null) {
            return null;
        }

        return $this->nestedValidator::SWAGGER_TYPE;
    }

    public function validateText(mixed $value): bool
    {
        return $this->validateJson($value);
    }

    public function validateJson(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        // validate all elements if there is a nested validator
        if ($this->nestedValidator != null) {
            foreach ($value as $element) {
                if (!$this->nestedValidator->validate($element)) {
                    return false;
                }
            }
        }
        return true;
    }
}
