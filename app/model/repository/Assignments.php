<?php

namespace App\Model\Repository;

use App\Model\Entity\Exercise;
use App\Model\Entity\User;
use App\Model\Entity\Group;
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

  public function isAssignedToUser(Exercise $exercise, User $user): bool {
    return $user->getGroups()->exists(function ($i, Group $group) use ($exercise) {
      return $group->getAssignments()->exists(function ($i, Assignment $assignment) use ($exercise) {
        return $assignment->getExercise() === $exercise;
      });
    });
  }
}
