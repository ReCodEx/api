<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates UUIDv4.
 */
class VUuid extends VString
{
    public function __construct(bool $strict = true)
    {
        parent::__construct(regex: "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", strict: $strict);
    }

    public function getExampleValue(): string
    {
        return "10000000-2000-4000-8000-160000000000";
    }
}
