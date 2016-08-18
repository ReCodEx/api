<?php

namespace App\Model\Helpers;

use Nette\Utils;

class Validators extends Utils\Validators {
  
  public static function init() {
    static::$validators['bool'] = [__CLASS__, 'isBool'];
    static::$validators['boolean'] = [__CLASS__, 'isBool'];
  }

  /**
   * Boolean validation for the string version coming from query string/form data
   * @param  [type]  $value [description]
   * @return boolean        [description]
   */
  public static function isBool($value) {
    return $value === "true" || $value === "false";
  }
}
