<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\Swagger\ParameterConstraints;

/**
 * Validates arrays and their nested elements.
 */
class VArray extends BaseValidator
{
    public const SWAGGER_TYPE = "array";

    // validator used for elements
    private ?BaseValidator $nestedValidator;

    /**
     * Creates an array validator.
     * @param ?BaseValidator $nestedValidator A validator that will be applied on all elements
     *  (validator arrays are not supported).
     */
    public function __construct(?BaseValidator $nestedValidator = null, bool $strict = true)
    {
        parent::__construct($strict);
        $this->nestedValidator = $nestedValidator;
    }

    public function getExampleValue(): string | null
    {
        if ($this->nestedValidator !== null) {
            return $this->nestedValidator->getExampleValue();
        }

        return null;
    }

    /**
     * @return string|null Returns the bottommost element swagger type. Can be null if the element validator is not set.
     */
    public function getElementSwaggerType(): mixed
    {
        // return null if the element type is unspecified
        if ($this->nestedValidator === null) {
            return null;
        }

        // traverse the VArray chain to get the final element type
        if ($this->nestedValidator instanceof VArray) {
            return $this->nestedValidator->getElementSwaggerType();
        }

        return $this->nestedValidator::SWAGGER_TYPE;
    }

    /**
     * @return int Returns the defined depth of the array.
     *  1 for arrays containing the final elements, 2 for arrays of arrays etc.
     */
    public function getArrayDepth(): int
    {
        if ($this->nestedValidator instanceof VArray) {
            return $this->nestedValidator->getArrayDepth() + 1;
        }

        return 1;
    }

    /**
     * @return ParameterConstraints Returns all parameter constrains of the bottommost element type that will be
     *  written into the generated swagger document. Returns null if there are no constraints.
     */
    public function getConstraints(): ?ParameterConstraints
    {
        return $this->nestedValidator?->getConstraints();
    }

    /**
     * Sets the strict flag for this validator and the element validator if present.
     * Expected to be changed by Attributes containing validators to change their behavior based on the Attribute type.
     * @param bool $strict Whether validation type checking should be done.
     *  When false, the validation step will no longer enforce the correct type of the value.
     */
    public function setStrict(bool $strict)
    {
        parent::setStrict($strict);
        $this->nestedValidator?->setStrict($strict);
    }

    public function validate(mixed $value): bool
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
