<?php
namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Entity\Assignment;
use App\Model\Entity\LocalizedExercise;
use App\Security\ACL\IAssignmentPermissions;

class AssignmentViewFactory {
  /** @var IAssignmentPermissions */
  private $assignmentAcl;

  public function __construct(IAssignmentPermissions $assignmentAcl) {
    $this->assignmentAcl = $assignmentAcl;
  }

  public function getAssignment(Assignment $assignment) {
    return [
      "id" => $assignment->getId(),
      "version" => $assignment->getVersion(),
      "isPublic" => $assignment->isPublic(),
      "createdAt" => $assignment->getCreatedAt()->getTimestamp(),
      "updatedAt" => $assignment->getUpdatedAt()->getTimestamp(),
      "localizedTexts" => $assignment->getLocalizedTexts()->map(function (LocalizedExercise $text) use ($assignment) {
        $data = $text->jsonSerialize();
        if (!$this->assignmentAcl->canViewDescription($assignment)) {
          unset($data["description"]);
        }

        $localizedAssignment = $assignment->getLocalizedAssignmentByLocale($text->getLocale());
        $data["studentHint"] = $localizedAssignment === null ? "" : $localizedAssignment->getStudentHint();

        return $data;
      })->getValues(),
      "exerciseId" => $assignment->getExercise()->getId(),
      "groupId" => $assignment->getGroup()->getId(),
      "firstDeadline" => $assignment->getFirstDeadline()->getTimestamp(),
      "secondDeadline" => $assignment->getSecondDeadline()->getTimestamp(),
      "allowSecondDeadline" => $assignment->getAllowSecondDeadline(),
      "maxPointsBeforeFirstDeadline" => $assignment->getMaxPointsBeforeFirstDeadline(),
      "maxPointsBeforeSecondDeadline" => $assignment->getMaxPointsBeforeSecondDeadline(),
      "visibleFrom" => $assignment->getVisibleFrom() ? $assignment->getVisibleFrom()->getTimestamp() : null,
      "submissionsCountLimit" => $assignment->getSubmissionsCountLimit(),
      "runtimeEnvironmentIds" => $assignment->getAllRuntimeEnvironmentsIds(),
      "disabledRuntimeEnvironmentIds" => $assignment->getDisabledRuntimeEnvironmentsIds(),
      "canViewLimitRatios" => $assignment->getCanViewLimitRatios(),
      "isBonus" => $assignment->isBonus(),
      "pointsPercentualThreshold" => $assignment->getPointsPercentualThreshold(),
      "exerciseSynchronizationInfo" => [
        "isSynchronizationPossible" => !$assignment->getExercise()->isBroken(),
        "updatedAt" => [
          "assignment" => $assignment->getUpdatedAt()->getTimestamp(),
          "exercise" => $assignment->getExercise()->getUpdatedAt()->getTimestamp(),
        ],
        "exerciseConfig" => [
          "upToDate" => $assignment->getExerciseConfig() === $assignment->getExercise()->getExerciseConfig(),
        ],
        "configurationType" => [
          "upToDate" => $assignment->getConfigurationType() === $assignment->getExercise()->getConfigurationType(),
        ],
        "scoreConfig" => [
          "upToDate" => $assignment->getScoreConfig() === $assignment->getExercise()->getScoreConfig(),
        ],
        "scoreCalculator" => [
          "upToDate" => $assignment->getScoreCalculator() === $assignment->getExercise()->getScoreCalculator(),
        ],
        "exerciseEnvironmentConfigs" => [
          "upToDate" => $assignment->areRuntimeEnvironmentConfigsInSync()
        ],
        "hardwareGroups" => [
          "upToDate" => $assignment->areHardwareGroupsInSync()
        ],
        "localizedTexts" => [
          "upToDate" => $assignment->areLocalizedTextsInSync()
        ],
        "limits" => [
          "upToDate" => $assignment->areLimitsInSync()
        ],
        "exerciseTests" => [
          "upToDate" => $assignment->areExerciseTestsInSync()
        ],
        "supplementaryFiles" => [
          "upToDate" => $assignment->areSupplementaryFilesInSync()
        ],
        "attachmentFiles" => [
          "upToDate" => $assignment->areAttachmentFilesInSync()
        ],
        "runtimeEnvironments" => [
          "upToDate" => $assignment->areRuntimeEnvironmentsInSync()
        ]
      ],
      "permissionHints" => PermissionHints::get($this->assignmentAcl, $assignment)
    ];
  }
}
