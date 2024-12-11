<?php

namespace App\Helpers\Swagger;

class PrimitiveFormatValidators
{
    /**
     * @format uuid
     */
    public function validateUuid($uuid)
    {
        if (!self::checkType($uuid, PhpTypes::String)) {
            return false;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) === 1;
    }

    private static function checkType($value, PhpTypes $type)
    {
        return gettype($value) === $type->value;
    }
}
