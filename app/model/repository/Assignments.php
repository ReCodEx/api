<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Assignment;

class Assignments extends BaseSoftDeleteRepository {

  public function replaceLocalizedTexts(Assignment $assignment, $localizations, $flush = TRUE) {
    // TODO solve duplicate code in Exercises class
    $originalLocalizations = $assignment->getLocalizedTexts()->toArray();

    foreach ($localizations as $localized) {
      $assignment->addLocalizedText($localized);
      $this->persist($localized);
    }

    foreach ($originalLocalizations as $localization) {
      $assignment->removeLocalizedText($localization);
    }

    if ($flush) {
      $this->flush();
    }
  }

  public function __construct(EntityManager $em) {
    parent::__construct($em, Assignment::class);
  }

}
