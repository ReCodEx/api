<?php

namespace App\Model\View;

use App\Helpers\EvaluationStatus\EvaluationStatus;
use App\Helpers\GroupBindings\GroupBindingAccessor;
use App\Helpers\Localizations;
use App\Helpers\Pair;
use App\Helpers\PermissionHints;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Group;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentPoints;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\ShadowAssignmentPointsRepository;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IShadowAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;
use Doctrine\Common\Collections\Collection;
use Nette\Utils\Arrays;

/**
 * Factory for group views which somehow do not fit into json serialization of
 * entities.
 */
class GroupViewFactory
{

    /** @var AssignmentSolutions */
    private $assignmentSolutions;

    /** @var IGroupPermissions */
    private $groupAcl;

    /** @var IAssignmentPermissions */
    private $assignmentAcl;

    /** @var IShadowAssignmentPermissions */
    private $shadowAssignmentAcl;

    /** @var GroupBindingAccessor */
    private $bindings;

    /** @var ShadowAssignmentPointsRepository */
    private $shadowAssignmentPointsRepository;

    public function __construct(
        AssignmentSolutions $assignmentSolutions,
        IGroupPermissions $groupAcl,
        IAssignmentPermissions $assignmentAcl,
        IShadowAssignmentPermissions $shadowAssignmentAcl,
        GroupBindingAccessor $bindings,
        ShadowAssignmentPointsRepository $shadowAssignmentPointsRepository
    ) {
        $this->assignmentSolutions = $assignmentSolutions;
        $this->groupAcl = $groupAcl;
        $this->assignmentAcl = $assignmentAcl;
        $this->shadowAssignmentAcl = $shadowAssignmentAcl;
        $this->bindings = $bindings;
        $this->shadowAssignmentPointsRepository = $shadowAssignmentPointsRepository;
    }


    /**
     * Get total sum of points which given user gained in given solutions.
     * @param AssignmentSolution[] $solutions list of assignment solutions
     * @return int
     */
    private function getPointsGainedByStudentForSolutions(array $solutions)
    {
        return array_reduce(
            $solutions,
            function ($carry, AssignmentSolution $solution) {
                return $carry + $solution->getTotalPoints();
            },
            0
        );
    }

    /**
     * Get total sum of points which given user gained in given shadow assignments.
     * @param ShadowAssignmentPoints[] $shadowPointsList
     * @return int
     */
    private function getPointsForShadowAssignments(array $shadowPointsList): int
    {
        return array_reduce(
            $shadowPointsList,
            function ($carry, ShadowAssignmentPoints $points) {
                return $carry + $points->getPoints();
            },
            0
        );
    }

    /**
     * Get the statistics of an individual student.
     * @param Group $group
     * @param User $student Student of this group
     * @param array $assignmentSolutions Loaded assignment solutions of the student for all assignments in the group.
     *                                   This varialble allows us bulk-load optimizations for the solutions.
     * @return array Students statistics
     */
    private function getStudentStatsInternal(Group $group, User $student, array $assignmentSolutions)
    {
        $maxPoints = $group->getMaxPoints();
        $shadowPointsMap = $this->shadowAssignmentPointsRepository->findPointsForAssignments(
            $group->getShadowAssignments()->getValues(),
            $student
        );
        $gainedPoints = $this->getPointsGainedByStudentForSolutions($assignmentSolutions);
        $gainedPoints += $this->getPointsForShadowAssignments($shadowPointsMap);

        $assignments = [];
        foreach ($group->getAssignments()->getValues() as $assignment) {
            /**
             * @var Assignment $assignment
             */
            $best = Arrays::get($assignmentSolutions, $assignment->getId(), null);
            $submission = $best ? $best->getLastSubmission() : null;

            $assignments[] = [
                "id" => $assignment->getId(),
                "status" => $submission ? EvaluationStatus::getStatus($submission) : null,
                "points" => [
                    "total" => $assignment->getMaxPointsBeforeFirstDeadline(),
                    "gained" => $best ? $best->getPoints() : null,
                    "bonus" => $best ? $best->getBonusPoints() : null,
                ],
                "bestSolutionId" => $best ? $best->getId() : null,
            ];
        }

        $shadowAssignments = [];
        foreach ($group->getShadowAssignments()->getValues() as $shadowAssignment) {
            /**
             * @var ShadowAssignment $shadowAssignment
             */
            $shadowPoints = Arrays::get($shadowPointsMap, $shadowAssignment->getId(), null);

            $shadowAssignments[] = [
                "id" => $shadowAssignment->getId(),
                "points" => [
                    "total" => $shadowAssignment->getMaxPoints(),
                    "gained" => $shadowPoints ? $shadowPoints->getPoints() : null
                ]
            ];
        }

        return [
            "userId" => $student->getId(),
            "groupId" => $group->getId(),
            "points" => [
                "total" => $maxPoints,
                "gained" => $gainedPoints
            ],
            "hasLimit" => $group->getThreshold() !== null && $group->getThreshold() > 0,
            "passesLimit" => $group->getThreshold(
            ) === null ? true : $gainedPoints >= $maxPoints * $group->getThreshold(),
            "assignments" => $assignments,
            "shadowAssignments" => $shadowAssignments
        ];
    }

    /**
     * Get the statistics of an individual student.
     * @param Group $group
     * @param User $student Student of this group
     * @return array Students statistics
     */
    public function getStudentsStats(Group $group, User $student)
    {
        $assignmentSolutions = $this->assignmentSolutions->findBestUserSolutionsForAssignments(
            $group->getAssignments()->getValues(),
            $student
        );
        return $this->getStudentStatsInternal($group, $student, $assignmentSolutions);
    }

    /**
     * Get the statistics of all students.
     * @param Group $group
     * @return array Statistics for all students of the group
     */
    public function getAllStudentsStats(Group $group)
    {
        $assignmentSolutions = $this->assignmentSolutions->findBestSolutionsForAssignments(
            $group->getAssignments()->getValues()
        );
        return array_map(
            function ($student) use ($group, $assignmentSolutions) {
                $solutions = array_key_exists(
                    $student->getId(),
                    $assignmentSolutions
                ) ? $assignmentSolutions[$student->getId()] : [];
                return $this->getStudentStatsInternal($group, $student, $solutions);
            },
            $group->getStudents()->getValues()
        );
    }

    /**
     * Get as much group detail info as your permissions grants you.
     * @param Group $group
     * @param bool $ignoreArchived
     * @return array
     */
    public function getGroup(Group $group, bool $ignoreArchived = true): array
    {
        $canView = $this->groupAcl->canViewDetail($group);
        $privateData = null;
        if ($canView) {
            $privateData = [
                "admins" => $group->getAdminsIds(),
                "supervisors" => $group->getSupervisors()->map(
                    function (User $s) {
                        return $s->getId();
                    }
                )->getValues(),
                "students" => $group->getStudents()->map(
                    function (User $s) {
                        return $s->getId();
                    }
                )->getValues(),
                "instanceId" => $group->getInstance() ? $group->getInstance()->getId() : null,
                "hasValidLicence" => $group->hasValidLicence(),
                "assignments" => $group->getAssignments()->filter(
                    function (Assignment $assignment) {
                        return $this->assignmentAcl->canViewDetail($assignment);
                    }
                )->map(
                    function (Assignment $assignment) {
                        return $assignment->getId();
                    }
                )->getValues(),
                "shadowAssignments" => $group->getShadowAssignments()->filter(
                    function (ShadowAssignment $assignment) {
                        return $this->shadowAssignmentAcl->canViewDetail($assignment);
                    }
                )->map(
                    function (ShadowAssignment $assignment) {
                        return $assignment->getId();
                    }
                )->getValues(),
                "publicStats" => $group->getPublicStats(),
                "threshold" => $group->getThreshold(),
                "bindings" => $this->bindings->getBindingsForGroup($group)
            ];
        }

        $childGroups = $group->getChildGroups()->filter(
            function (Group $group) use ($ignoreArchived) {
                return !($ignoreArchived && $group->isArchived()) && $this->groupAcl->canViewPublicDetail($group);
            }
        );

        return [
            "id" => $group->getId(),
            "externalId" => $group->getExternalId(),
            "organizational" => $group->isOrganizational(),
            "archived" => $group->isArchived(),
            "public" => $group->isPublic(),
            "directlyArchived" => $group->isDirectlyArchived(),
            "localizedTexts" => $group->getLocalizedTexts()->getValues(),
            "primaryAdminsIds" => $group->getPrimaryAdmins()->map(
                function (User $user) {
                    return $user->getId();
                }
            )->getValues(),
            "parentGroupId" => $group->getParentGroup() ? $group->getParentGroup()->getId() : null,
            "parentGroupsIds" => $group->getParentGroupsIds(),
            "childGroups" => $childGroups->map(
                function (Group $group) {
                    return $group->getId();
                }
            )->getValues(),
            "privateData" => $privateData,
            "permissionHints" => PermissionHints::get($this->groupAcl, $group)
        ];
    }

    /**
     * Get group data which current user can view about given groups.
     * @param Group[] $groups
     * @param bool $ignoreArchived
     * @return array
     */
    public function getGroups(array $groups, bool $ignoreArchived = true): array
    {
        return array_map(
            function (Group $group) use ($ignoreArchived) {
                return $this->getGroup($group, $ignoreArchived);
            },
            $groups
        );
    }
}
