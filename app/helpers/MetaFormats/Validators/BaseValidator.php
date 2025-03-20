<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\Swagger\ParameterConstraints;

/**
 * Base class for all validators.
 */
class BaseValidator
{
    public function __construct(bool $strict = true)
    {
        $this->strict = $strict;
    }

    /**
     * @var string One of the valid swagger types (https://swagger.io/docs/specification/v3_0/data-models/data-types/).
     */
    public const SWAGGER_TYPE = "invalid";

    /**
     * @var bool Whether strict type checking is done in validation.
     */
    protected bool $strict;

    /**
     * Sets the strict flag.
     * Expected to be changed by Attributes containing validators to change their behavior based on the Attribute type.
     * @param bool $strict Whether validation type checking should be done.
     *  When false, the validation step will no longer enforce the correct type of the value.
     */
    public function setStrict(bool $strict)
    {
        $this->strict = $strict;
    }

    /**
     * @return string Returns a sample expected value to be validated by the validator.
     *  This value will be used in generated swagger documents.
     *  Can return null, signalling to the swagger generator to omit the example field.
     */
    public function getExampleValue(): string | null
    {
        return null;
    }

    /**
     * @return ParameterConstraints Returns all parameter constrains that will be written into the generated
     *  swagger document. Returns null if there are no constraints.
     */
    public function getConstraints(): ?ParameterConstraints
    {
        // there are no default constraints
        return null;
    }

    /**
     * Validates a value with the configured validation strictness.
     * @param mixed $value The value to be validated.
     * @return bool Whether the value passed the test.
     */
    public function validate(mixed $value): bool
    {
        // return false by default to enforce overriding in derived types
        return false;
    }
}
