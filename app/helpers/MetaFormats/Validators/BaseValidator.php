<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Base class for all validators.
 */
class BaseValidator
{
    /**
     * @var string One of the valid swagger types (https://swagger.io/docs/specification/v3_0/data-models/data-types/).
     */
    public const SWAGGER_TYPE = "invalid";

    /**
     * @var bool If true, the validateJson method will be used instead of the validateText one for validation.
     *  Intended to be changed by Attributes containing validators to change their behavior based on the Attribute type.
     */
    public bool $useJsonValidation = true;

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
     * Validates a value retrieved from unstructured data sources, such as query parameters.
     * @param mixed $value The value to be validated.
     * @return bool Whether the value passed the test.
     */
    public function validateText(mixed $value): bool
    {
        // return false by default to enforce overriding in derived types
        return false;
    }

    /**
     * Validates a value retrieved from json files (usually request bodies).
     * @param mixed $value The value to be validated.
     * @return bool Whether the value passed the test.
     */
    public function validateJson(mixed $value): bool
    {
        // return false by default to enforce overriding in derived types
        return false;
    }

    /**
     * Validates a value with the configured validator method.
     * @param mixed $value The value to be validated.
     * @return bool Whether the value passed the test.
     */
    public function validate(mixed $value): bool
    {
        if ($this->useJsonValidation) {
            return $this->validateJson($value);
        }
        return $this->validateText($value);
    }
}
