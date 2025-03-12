<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates strings.
 */
class VString extends BaseValidator
{
    public const SWAGGER_TYPE = "string";
    private int $minLength;
    private int $maxLength;
    private ?string $regex;

    /**
     * Constructs a string validator.
     * @param int $minLength The minimal length of the string.
     * @param int $maxLength The maximal length of the string, or -1 for unlimited length.
     * @param ?string $regex Regex pattern used for validation.
     *  Evaluated with the preg_match function with this argument as the pattern.
     */
    public function __construct(int $minLength = 0, int $maxLength = -1, ?string $regex = null)
    {
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->regex = $regex;
    }

    public function getExampleValue()
    {
        return "text";
    }

    public function validateText(mixed $value): bool
    {
        return $this->validateJson($value);
    }

    public function validateJson(mixed $value): bool
    {
        // do not allow other types
        if (!is_string($value)) {
            return false;
        }

        // check length
        $length = strlen($value);
        if ($length < $this->minLength) {
            return false;
        }
        if ($this->maxLength !== -1 && $length > $this->maxLength) {
            return false;
        }

        // check regex
        if ($this->regex === null) {
            return true;
        }

        return preg_match($this->regex, $value) === 1;
    }
}
