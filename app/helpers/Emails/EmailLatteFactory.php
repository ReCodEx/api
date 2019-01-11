<?php

namespace App\Helpers\Emails;

use Latte;

/**
 * Factory for latte engine which can be used in email senders.
 */
class EmailLatteFactory {

  /**
   * Create latte engine for email templates with helper filters.
   * @return Latte\Engine
   */
  public static function latte(): Latte\Engine {
    $latte = new Latte\Engine();

    // filters
    $latte->addFilter("localizedDate", function ($date, $locale) {
      if ($locale === EmailLocalizationHelper::CZECH_LOCALE) {
        return Latte\Runtime\Filters::date($date, 'j.n.Y H:i');
      }

      return Latte\Runtime\Filters::date($date, 'n/j/Y H:i');
    });

    return $latte;
  }
}
