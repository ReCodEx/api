<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates emails.
 */
class VEmail extends VString
{
    public function __construct()
    {
        // the email should not be empty
        parent::__construct(1);
    }

    public function getExampleValue(): string
    {
        return "name@domain.tld";
    }

    public function validateText(mixed $value): bool
    {
        return $this->validateJson($value);
    }

    public function validateJson(mixed $value): bool
    {
        if (!parent::validateJson($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) != false;
    }
}
