<?php

namespace App\Model\View;

use App\Helpers\Localizations;
use App\Helpers\PermissionHints;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTag;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Repository\ExerciseFileLinks;
use App\Security\ACL\IExercisePermissions;

class ExerciseViewFactory
{
    private $exercisePermissions;
    private $fileLinks;

    public function __construct(IExercisePermissions $exercisePermissions, ExerciseFileLinks $fileLinks)
    {
        $this->exercisePermissions = $exercisePermissions;
        $this->fileLinks = $fileLinks;
    }

    /**
     * Helper function that reduces localized texts to bare minimum (only name remains).
     * @param LocalizedExercise[] $localizedTexts to be reduced
     * @return array
     */
    private static function restrictToLocalizedName(array $localizedTexts): array
    {
        return array_map(function ($text) {
            return [
                "locale" => $text->getLocale(),
                "name" => $text->getName(),
            ];
        }, $localizedTexts);
    }

    public function getExercise(Exercise $exercise)
    {
        /** @var LocalizedExercise|null $primaryLocalization */
        $primaryLocalization = Localizations::getPrimaryLocalization($exercise->getLocalizedTexts());
        $forkedFrom = $exercise->getForkedFrom();

        return [
            "id" => $exercise->getId(),
            "version" => $exercise->getVersion(),
            "createdAt" => $exercise->getCreatedAt()->getTimestamp(),
            "updatedAt" => $exercise->getUpdatedAt()->getTimestamp(),
            "archivedAt" => $exercise->isArchived() ? $exercise->getArchivedAt()->getTimestamp() : null,
            "localizedTexts" => $exercise->getLocalizedTexts()->getValues(),
            "localizedTextsLinks" => $this->fileLinks->getLinksMapForExercise($exercise->getId()),
            "difficulty" => $exercise->getDifficulty(),
            "runtimeEnvironments" => $exercise->getRuntimeEnvironments()->getValues(),
            "hardwareGroups" => $exercise->getHardwareGroups()->getValues(),
            "forkedFrom" => $forkedFrom ? $forkedFrom->getId() : null,
            "authorId" => $exercise->getAuthor() ? $exercise->getAuthor()->getId() : null,
            "adminsIds" => $exercise->getAdminsIds(),
            "groupsIds" => $exercise->getGroupsIds(),
            "mergeJudgeLogs" => $exercise->getMergeJudgeLogs(),
            "filesIds" => $exercise->getExerciseFilesIds(),
            "configurationType" => $exercise->getConfigurationType(),
            "isPublic" => $exercise->isPublic(),
            "isLocked" => $exercise->isLocked(),
            "isBroken" => $exercise->isBroken(),
            "validationError" => $exercise->getValidationError(),
            "hasReferenceSolutions" => !$exercise->getReferenceSolutions(ReferenceExerciseSolution::VISIBILITY_PRIVATE)
                ->isEmpty(), // temporary solutions are skipped
            "tags" => array_values(
                $exercise->getTags()->map(
                    function (ExerciseTag $tag) {
                        return $tag->getName();
                    }
                )->toArray()
            ),
            "solutionFilesLimit" => $exercise->getSolutionFilesLimit(),
            "solutionSizeLimit" => $exercise->getSolutionSizeLimit(),
            "permissionHints" => PermissionHints::get($this->exercisePermissions, $exercise),

            // DEPRECATED fields (will be removed in future)
            "name" => $primaryLocalization ? $primaryLocalization->getName() : "",
            "description" => $primaryLocalization ? $primaryLocalization->getDescription() : "",
            "supplementaryFilesIds" => $exercise->getExerciseFilesIds(),
            "attachmentFilesIds" => $exercise->getAttachmentFilesIds(),
        ];
    }

    /**
     * Returns bare minimum of an exercise (localized names, author, and permission hint for view detail).
     */
    public function getExerciseBareMinimum(Exercise $exercise)
    {
        return [
            "id" => $exercise->getId(),
            "localizedTexts" => self::restrictToLocalizedName($exercise->getLocalizedTexts()->getValues()),
            "authorId" => $exercise->getAuthor() ? $exercise->getAuthor()->getId() : null,
            "canViewDetail" => $this->exercisePermissions->canViewDetail($exercise),
        ];
    }
}
