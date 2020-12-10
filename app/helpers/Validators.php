<?php

namespace App\Helpers;

use DateTime;
use Nette\Utils;

/**
 * Custom validators extension
 */
class Validators extends Utils\Validators
{

    /**
     * Registering new validating functions
     */
    public static function init()
    {
        static::$validators['timestamp'] = [self::class, 'isTimestamp'];
    }

    public static function isTimestamp($value)
    {
        return static::isNumericInt($value) && intval($value) >= 0;
    }

    /**
     * Prepare values for validation - according to validation rules.
     * @param string $value Value
     * @param string $validationRule Validation rule to be applied to the value
     * @return string|int|bool|float  Preprocessed value
     */
    public static function preprocessValue($value, $validationRule)
    {
        $options = explode('|', $validationRule);
        foreach ($options as $item) {
            $item = explode(':', $item, 2);
            if ($item[0] == 'bool' || $item[0] == 'boolean') {
                // converts all possible string representations of boolean to boolean
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }
        return $value;
    }

    public static function is($value, $expected): bool
    {
        return parent::is($value, $expected);
    }
}
