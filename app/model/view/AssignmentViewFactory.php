<?php

namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Entity\Assignment;
use App\Model\Entity\LocalizedExercise;
use App\Security\ACL\IAssignmentPermissions;

class AssignmentViewFactory
{
    /** @var IAssignmentPermissions */
    private $assignmentAcl;

    public function __construct(IAssignmentPermissions $assignmentAcl)
    {
        $this->assignmentAcl = $assignmentAcl;
    }

    public function getAssignments(array $assignments): array
    {
        return array_map(
            function (Assignment $assignment) {
                return $this->getAssignment($assignment);
            },
            $assignments
        );
    }

    public function getAssignment(Assignment $assignment)
    {
        $exercise = $assignment->getExercise();
        return [
            "id" => $assignment->getId(),
            "version" => $assignment->getVersion(),
            "isPublic" => $assignment->isPublic(),
            "createdAt" => $assignment->getCreatedAt()->getTimestamp(),
            "updatedAt" => $assignment->getUpdatedAt()->getTimestamp(),
            "localizedTexts" => $assignment->getLocalizedTexts()->map(
                function (LocalizedExercise $text) use ($assignment) {
                    $data = $text->jsonSerialize();
                    if (!$this->assignmentAcl->canViewDescription($assignment)) {
                        unset($data["description"]);
                    }

                    $localizedAssignment = $assignment->getLocalizedAssignmentByLocale($text->getLocale());
                    $data["studentHint"] = $localizedAssignment === null ? "" : $localizedAssignment->getStudentHint();

                    return $data;
                }
            )->getValues(),
            "exerciseId" => $exercise ? $exercise->getId() : null,
            "groupId" => $assignment->getGroup() ? $assignment->getGroup()->getId() : null,
            "firstDeadline" => $assignment->getFirstDeadline()->getTimestamp(),
            "secondDeadline" => $assignment->getSecondDeadline()->getTimestamp(),
            "allowSecondDeadline" => $assignment->getAllowSecondDeadline(),
            "maxPointsBeforeFirstDeadline" => $assignment->getMaxPointsBeforeFirstDeadline(),
            "maxPointsBeforeSecondDeadline" => $assignment->getMaxPointsBeforeSecondDeadline(),
            "maxPointsDeadlineInterpolation" => $assignment->getMaxPointsDeadlineInterpolation(),
            "visibleFrom" => $assignment->getVisibleFrom() ? $assignment->getVisibleFrom()->getTimestamp() : null,
            "submissionsCountLimit" => $assignment->getSubmissionsCountLimit(),
            "runtimeEnvironmentIds" => $assignment->getAllRuntimeEnvironmentsIds(),
            "disabledRuntimeEnvironmentIds" => $assignment->getDisabledRuntimeEnvironmentsIds(),
            "canViewLimitRatios" => $assignment->getCanViewLimitRatios(),
            "canViewMeasuredValues" => $assignment->getCanViewMeasuredValues(),
            "canViewJudgeStdout" => $assignment->getCanViewJudgeStdout(),
            "canViewJudgeStderr" => $assignment->getCanViewJudgeStderr(),
            "mergeJudgeLogs" => $assignment->getMergeJudgeLogs(),
            "isExam" => $assignment->isExam(),
            "isBonus" => $assignment->isBonus(),
            "pointsPercentualThreshold" => $assignment->getPointsPercentualThreshold() * 100,
            "exerciseSynchronizationInfo" => [
                "isSynchronizationPossible" => $exercise && !$exercise->isBroken(),
                "updatedAt" => [
                    "assignment" => $assignment->getSyncedAt()->getTimestamp(),
                    "exercise" => $exercise ? $exercise->getUpdatedAt()->getTimestamp() : null,
                ],
                "exerciseConfig" => [
                    "upToDate" => $exercise && $assignment->getExerciseConfig() === $exercise->getExerciseConfig(),
                ],
                "configurationType" => [
                    "upToDate" => $exercise
                        && $assignment->getConfigurationType() === $exercise->getConfigurationType(),
                ],
                "scoreConfig" => [
                    "upToDate" => $exercise
                        && $assignment->getScoreConfig()->getId() === $exercise->getScoreConfig()->getId(),
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
                ],
                "mergeJudgeLogs" => [
                    "upToDate" => $exercise && $assignment->getMergeJudgeLogs() === $exercise->getMergeJudgeLogs(),
                ],
            ],
            "solutionFilesLimit" => $assignment->getSolutionFilesLimit(),
            "solutionSizeLimit" => $assignment->getSolutionSizeLimit(),
            "plagiarismCheckedAt" => $assignment->getPlagiarismBatch()?->getUploadCompletedAt()?->getTimestamp(),
            "permissionHints" => PermissionHints::get($this->assignmentAcl, $assignment)
        ];
    }
}
