<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates emails.
 */
class VEmail extends VString
{
    public function __construct(bool $strict = true)
    {
        // the email should not be empty
        parent::__construct(1, strict: $strict);
    }

    public function getExampleValue(): string
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
