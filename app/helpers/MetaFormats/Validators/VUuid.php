<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates UUIDv4.
 */
class VUuid extends VString
{
    public function __construct()
    {
        parent::__construct(regex: "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/");
    }

    public function getExampleValue()
    {
        return "10000000-2000-4000-8000-160000000000";
    }

    public function validate(mixed $value): bool
    {
        return parent::validate($value);
    }
}
