<?php

namespace App\Model\Helpers;

use Nette\Utils;

class Validators extends Utils\Validators {
  
  public static function init() {
    static::$validators['datetime'] = [__CLASS__, 'isDateTime'];
  }

  /**
   * Datetime validation
   * @param  DateTime  $value
   * @return boolean
   */
  public static function isDateTime($value) {
    return $value instanceof \DateTime;
  }

  /**
   * Prepare values for validation - according to validation rules.
   * @param  string $value          Value
   * @param  string $validationRule Validation rule to be applied to the value
   * @return string|int|bool|float  Preprocessed value
   */
  public static function preprocessValue($value, $validationRule) {
    foreach (explode('|', $validationRule) as $item) {
      $item = explode(':', $item, 2);
      if ($item[0] == 'bool' || $item[0] == 'boolean') {
        // converts all possible string representations of boolean to boolean
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
      } else if ($item[0] == 'datetime') {
        // validate datetime in ISO 8601 format, regex taken from:
        // http://stackoverflow.com/questions/8003446/php-validate-iso-8601-date-string
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|(\+|-)\d{2}(:?\d{2})?)$/', $value)) {
          return new \DateTime($value);
        }
      }
    }
    return $value;
  }
}
