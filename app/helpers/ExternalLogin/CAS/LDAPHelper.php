<?php

namespace App\Helpers\ExternalLogin\CAS;

/**
 * LDAP helper functions.
 */
class LDAPHelper
{

    /**
     * Get scalar value of given attribute.
     * @param mixed $value
     * @return mixed
     */
    public static function getScalar($value)
    {
        if (is_scalar($value)) {
            return $value;
        }
        return current($value);
    }

    /**
     * Get array value of given attribute.
     * @param mixed $value
     * @return array
     */
    public static function getArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return [$value];
    }
}
