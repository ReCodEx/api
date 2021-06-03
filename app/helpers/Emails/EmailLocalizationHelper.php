<?php

namespace App\Helpers\Emails;

use App\Exceptions\InvalidStateException;
use App\Model\Entity\LocalizedEntity;
use App\Model\Entity\User;
use Doctrine\Common\Collections\Collection;
use Nette;
use DateInterval;

/**
 * Email localization helper for identifying the right template file and right
 * localization from given ones, based on user preferences.
 */
class EmailLocalizationHelper
{
    public const CZECH_LOCALE = "cs";
    public const DEFAULT_LOCALE = "en";
    public const LOCALE_PLACEHOLDER_PATTERN = '/{locale}/';


    /**
     * Based on given collection try to find localized text conforming given
     * locale or conforming to the default locale.
     * @param string $locale
     * @param Collection $collection
     * @return mixed|null
     */
    public static function getLocalization(string $locale, Collection $collection)
    {
        $defaultText = null;
        /** @var LocalizedEntity $text */
        foreach ($collection as $text) {
            if ($text->getLocale() === $locale) {
                return $text;
            }

            if ($text->getLocale() === self::DEFAULT_LOCALE) {
                $defaultText = $text;
            }
        }

        if ($defaultText !== null) {
            return $defaultText;
        }

        return !$collection->isEmpty() ? $collection->first() : null;
    }

    /**
     * Based on given template path and filename with '{locale}' substring find
     * proper template file with given locale or with default one.
     * @param string $locale
     * @param string $templatePath
     * @return string
     * @throws InvalidStateException
     */
    public static function getTemplate(string $locale, string $templatePath): string
    {
        $template = Nette\Utils\Strings::replace($templatePath, self::LOCALE_PLACEHOLDER_PATTERN, $locale);
        if (is_file($template)) {
            return $template;
        }

        $template = Nette\Utils\Strings::replace($templatePath, self::LOCALE_PLACEHOLDER_PATTERN, self::DEFAULT_LOCALE);
        if (is_file($template)) {
            return $template;
        }

        throw new InvalidStateException("Missing email template for '$templatePath'");
    }

    /**
     * Process given users and prepare sending emails to all possible locales of
     * users. Sending itself should be done via given method, which will be
     * executed for each locale.
     * @param User[] $users
     * @param callable $execute three arguments, first is array of users,
     *        second locale and third resolved template path, returning value
     *        should be boolean if the sending was done successfully
     * @return bool if all emails were sent successfully
     */
    public function sendLocalizedEmail(array $users, callable $execute): bool
    {
        // prepare array indexed by locale and containing user with that locale
        $localeUsers = [];
        foreach ($users as $user) {
            $defaultLanguage = $user->getSettings()->getDefaultLanguage();
            if (!array_key_exists($defaultLanguage, $localeUsers)) {
                $localeUsers[$defaultLanguage] = [];
            }
            $localeUsers[$defaultLanguage][] = $user;
        }

        // go through and send the messages via given executable method
        $result = true;
        foreach ($localeUsers as $locale => $toUsers) {
            $emails = array_map(
                function (User $user) {
                    return $user->getEmail();
                },
                $toUsers
            );

            if (!$execute($toUsers, $emails, $locale)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     *
     */
    private static $localizations = [
        EmailLocalizationHelper::CZECH_LOCALE => [
            // this is actually not perfect, but it will do for now
            'y' => [ 1 => "rok", 2 => "roky", 5 => "let" ],
            'm' => [ 1 => "měsíc", 2 => "měsíce", 5 => "měsíců" ],
            'd' => [ 1 => "den", 2 => "dny", 5 => 'dní' ],
            'h' => [ 1 => "hodina", 2 => "hodiny", 5 => "hodin" ],
            'i' => [ 1 => "minuta", 2 => "minuty", 5 => "minut" ],
            's' => [ 1 => "vteřina", 2 => "vteřiny", 5 => "vteřin" ],
        ],
        EmailLocalizationHelper::DEFAULT_LOCALE => [ // default (English)
            'y' => [ 1 => "year", 2 => "years" ],
            'm' => [ 1 => "month", 2 => "months" ],
            'd' => [ 1 => "day", 2 => "days" ],
            'h' => [ 1 => "hour", 2 => "hours" ],
            'i' => [ 1 => "minute", 2 => "minutes" ],
            's' => [ 1 => "second", 2 => "seconds" ],
        ]
    ];

    /**
     * Helps find the right suffix (considering forms of plural) for a given value.
     * @param int $value for which we find the suffix
     * @param array $suffixes [ minValue => suffixString ]
     * @return string suffix
     */
    private static function getDateIntervalLocalizationSuffix(int $value, array $suffixes): string
    {
        $bestSuffix = reset($suffixes);
        foreach ($suffixes as $min => $suffix) {
            if ($min <= $value) {
                $bestSuffix = $suffix;
            }
        }
        return $bestSuffix;
    }

    /**
     * Return localized string with an informal description of relative time (date interval).
     * It writes out up to two the most significant values, so it is brief yet it scales from seconds to years.
     * The interval is treated as absoluted value (ignoring the sign).
     * @param DateInterval $dateDiff interval to be displayed
     * @param string $locale identification of the language
     * @return string formatted relative time
     */
    public static function getDateIntervalLocalizedString(DateInterval $dateDiff, string $locale): string
    {
        $localization = array_key_exists($locale, self::$localizations)
            ? self::$localizations[$locale] : self::$localizations[EmailLocalizationHelper::DEFAULT_LOCALE];

        $result = [];
        foreach ($localization as $key => $suffixes) {
            if (count($result) >= 2) {
                break; // two subsequent values are enough for precision
            }
            $value = $dateDiff->$key;
            if ($value > 0) {
                $bestSuffix = self::getDateIntervalLocalizationSuffix($value, $suffixes);
                $result[] = "$value $bestSuffix";
            } elseif ($result) {
                break; // this should have been second value, but it was empty
            }
        }

        return join(', ', $result);
    }
}
