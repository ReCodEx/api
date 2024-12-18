<?php

namespace App\Helpers\MetaFormats;

class PrimitiveFormatValidators
{
    /**
     * @format uuid
     */
    public function validateUuid(string $uuid): bool
    {
        if (!self::checkType($uuid, PhpTypes::String)) {
            return false;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) === 1;
    }

    /**
     * @format email
     */
    public function validateEmail(string $email): bool
    {
        if (!self::checkType($email, PhpTypes::String)) {
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return true;
    }

    private static function checkType($value, PhpTypes $type): bool
    {
        return gettype($value) === $type->value;
    }
}
