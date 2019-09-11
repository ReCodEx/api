<?php

namespace App\Helpers\Emails;

use App\Exceptions\InvalidStateException;
use App\Model\Entity\LocalizedEntity;
use App\Model\Entity\User;
use Doctrine\Common\Collections\Collection;
use Nette;

/**
 * Email localization helper for identifying the right template file and right
 * localization from given ones, based on user preferences.
 */
class EmailLocalizationHelper {

  const CZECH_LOCALE = "cs";
  const DEFAULT_LOCALE = "en";
  const LOCALE_PLACEHOLDER_PATTERN = '/{locale}/';


  /**
   * Based on given collection try to find localized text conforming given
   * locale or conforming to the default locale.
   * @param string $locale
   * @param Collection $collection
   * @return mixed
   */
  public static function getLocalization(string $locale, Collection $collection) {
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
  public static function getTemplate(string $locale, string $templatePath): string {
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
  public function sendLocalizedEmail(array $users, callable $execute): bool {
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
      $emails = array_map(function (User $user) {
        return $user->getEmail();
      }, $toUsers);

      if (!$execute($toUsers, $emails, $locale)) {
        $result = false;
      }
    }

    return $result;
  }
}
