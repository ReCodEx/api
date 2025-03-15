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

    public function getExampleValue(): string
    {
        return "10000000-2000-4000-8000-160000000000";
    }

    public function validateText(mixed $value): bool
    {
        return $this->validateJson($value);
    }

    public function validateJson(mixed $value): bool
    {
        return parent::validateJson($value);
    }
}
