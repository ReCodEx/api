<?php

namespace App\Helpers\Emails;

use Nette;

/**
 * Email link helper which is able to find and replace parameters in URLs,
 * parameters are in form of '{var}' and this helper treats them as variables.
 */
class EmailLinkHelper {

  /**
   * In given link find and replace variables contained in given array.
   * Array is indexed by variable name and contains its value.
   * @param string $link
   * @param array $vars
   * @return string
   */
  public static function getLink(string $link, array $vars): string {
    foreach ($vars as $var => $val) {
      $link = Nette\Utils\Strings::replace($link, "/\{$var\}/", $val);
    }
    return $link;
  }
}
