<?php
namespace App\Model\View;

use App\Helpers\Localizations;
use App\Helpers\PermissionHints;
use App\Model\Entity\Exercise;
use App\Model\Entity\LocalizedExercise;
use App\Security\ACL\IExercisePermissions;

class ExerciseViewFactory {
  private $exercisePermissions;

  public function __construct(IExercisePermissions $exercisePermissions) {
    $this->exercisePermissions = $exercisePermissions;
  }

  public function getExercise(Exercise $exercise) {
    /** @var LocalizedExercise $primaryLocalization */
    $primaryLocalization = Localizations::getPrimaryLocalization($exercise->getLocalizedTexts());
    $forkedFrom = $exercise->getForkedFrom();

    return [
      "id" => $exercise->getId(),
      "name" => $primaryLocalization ? $primaryLocalization->getName() : "", # BC
      "version" => $exercise->getVersion(),
      "createdAt" => $exercise->getCreatedAt()->getTimestamp(),
      "updatedAt" => $exercise->getUpdatedAt()->getTimestamp(),
      "localizedTexts" => $exercise->getLocalizedTexts()->getValues(),
      "difficulty" => $exercise->getDifficulty(),
      "runtimeEnvironments" => $exercise->getRuntimeEnvironments()->getValues(),
      "hardwareGroups" => $exercise->getHardwareGroups()->getValues(),
      "forkedFrom" => $forkedFrom ? $forkedFrom->getId() : null,
      "authorId" => $exercise->getAuthor()->getId(),
      "groupsIds" => $exercise->getGroupsIds(),
      "isPublic" => $exercise->isPublic(),
      "isLocked" => $exercise->isLocked(),
      "description" => $primaryLocalization ? $primaryLocalization->getDescription() : "", # BC
      "supplementaryFilesIds" => $exercise->getSupplementaryFilesIds(),
      "attachmentFilesIds" => $exercise->getAttachmentFilesIds(),
      "configurationType" => $exercise->getConfigurationType(),
      "isBroken" => $exercise->isBroken(),
      "validationError" => $exercise->getValidationError(),
      "permissionHints" => PermissionHints::get($this->exercisePermissions, $exercise)
    ];
  }
}
