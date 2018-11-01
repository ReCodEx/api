<?php

namespace App\Helpers;

use App\Model\Entity\LocalizedEntity;
use App\Security\Identity;
use Doctrine\Common\Collections\Collection;
use Nette;

/**
 * Email localization helper for identifying the right template file and right
 * localization from given ones, based on user preferences.
 */
class EmailLocalizationHelper {

  const DEFAULT_LOCALE = "en";

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
   * Based on given collection try to find localized text conforming the default
   * language preferred by user.
   * @param Collection $collection
   * @return mixed
   */
  public function getLocalization(Collection $collection) {
    /** @var Identity $userIdentity */
    $userIdentity = $this->user->getIdentity();
    $userLocale = $userIdentity->getUserData() ? $userIdentity->getUserData()->getSettings()->getDefaultLanguage() : self::DEFAULT_LOCALE;

    /** @var LocalizedEntity $text */
    foreach ($collection as $text) {
      if ($text->getLocale() === $userLocale) {
        return $text;
      }
    }

    return !$collection->isEmpty() ? $collection->first() : null;
  }

  /**
   * TODO
   * @param string $templatePath
   */
  public function getTemplate(string $templatePath) {
    //
  }
}
