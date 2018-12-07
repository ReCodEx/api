<?php

namespace App\Helpers\Emails;

use App\Exceptions\InvalidStateException;
use App\Model\Entity\LocalizedEntity;
use App\Security\Identity;
use Doctrine\Common\Collections\Collection;
use Nette;

/**
 * Email localization helper for identifying the right template file and right
 * localization from given ones, based on user preferences.
 */
class EmailLocalizationHelper {

  const CZECH_LOCALE = "cs";
  const DEFAULT_LOCALE = "en";
  const LOCALE_PLACEHOLDER_PATTERN = "[{locale}]";

  /**
   * @var Nette\Security\User
   */
  private $user;

  /**
   * EmailLocalizationHelper constructor.
   * @param Nette\Security\User $user
   */
  public function __construct(Nette\Security\User $user) {
    $this->user = $user;
  }


  /**
   * Get preferred locale of current user or default one.
   * @return string
   */
  private function getUserLocale(): string {
    /** @var Identity $userIdentity */
    $userIdentity = $this->user->getIdentity();
    return $userIdentity->getUserData() ? $userIdentity->getUserData()->getSettings()->getDefaultLanguage() : self::DEFAULT_LOCALE;
  }

  /**
   * Based on given collection try to find localized text conforming the default
   * language preferred by user.
   * @param Collection $collection
   * @return mixed
   */
  public function getLocalization(Collection $collection) {
    $userLocale = $this->getUserLocale();

    /** @var LocalizedEntity $text */
    foreach ($collection as $text) {
      if ($text->getLocale() === $userLocale) {
        return $text;
      }
    }

    return !$collection->isEmpty() ? $collection->first() : null;
  }

  /**
   * Based on given template path and filename with '{locale}' substring find
   * proper template file with locale preferred by user or with default one.
   * @param string $templatePath
   * @return string
   * @throws InvalidStateException
   */
  public function getTemplate(string $templatePath): string {
    $userLocale = $this->getUserLocale();
    $template = Nette\Utils\Strings::replace($templatePath, self::LOCALE_PLACEHOLDER_PATTERN, $userLocale);
    if (is_file($template)) {
      return $template;
    }

    $template = Nette\Utils\Strings::replace($templatePath, self::LOCALE_PLACEHOLDER_PATTERN, self::DEFAULT_LOCALE);
    if (is_file($template)) {
      return $template;
    }

    throw new InvalidStateException("Missing email template for '$templatePath'");
  }
}
