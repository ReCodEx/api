<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Assignment;

class Assignments extends BaseSoftDeleteRepository {

  public function replaceLocalizedAssignments(Assignment $assignment, $localizations, $flush = TRUE) {
    // TODO solve duplicate code in Exercises class
    $originalLocalizations = $assignment->getLocalizedAssignments()->toArray();

    foreach ($localizations as $localizedAssignment) {
      $assignment->addLocalizedAssignment($localizedAssignment);
      $this->persist($localizedAssignment);
    }

    foreach ($originalLocalizations as $localization) {
      $assignment->removeLocalizedAssignment($localization);
    }

    if ($flush) {
      $this->flush();
    }
  }

  public function __construct(EntityManager $em) {
    parent::__construct($em, Assignment::CLASS);
  }

}
