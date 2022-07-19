<?php

namespace App\Helpers\Emails;

use Latte;
use Latte\Engine;
use Latte\Essential\Filters;

/**
 * Factory for latte engine which can be used in email senders.
 * Constructs an instance of EmailLatteWrapper and inject latte engine inside.
 */
class EmailLatteFactory
{

    /**
     * Create latte engine for email templates with helper filters.
     * @return EmailLatteWrapper
     */
    public static function latte(): EmailLatteWrapper
    {
        $latte = new Engine();
        $latte->setTempDirectory(__DIR__ . "/../../../temp");

        // extra tag(s) for emails
        //$latte->addMacro("emailSubject", EmailMacros::install($latte->getCompiler()));
        $latte->addExtension(new EmailLatteExtension());

        // filters
        $latte->addFilter(
            "localizedDate",
            function ($date, $locale) {
                if ($locale === EmailLocalizationHelper::CZECH_LOCALE) {
                    return Filters::date($date, 'j.n.Y H:i');
                }

                return Filters::date($date, 'n/j/Y H:i');
            }
        );

        $latte->addFilter(
            "relativeDateTime",
            function ($dateDiff, $locale) {
                return EmailLocalizationHelper::getDateIntervalLocalizedString($dateDiff, $locale);
            }
        );

        return new EmailLatteWrapper($latte);
    }
}
