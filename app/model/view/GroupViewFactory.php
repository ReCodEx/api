<?php

namespace App\Model\View;

use App\Helpers\EvaluationStatus\EvaluationStatus;
use App\Helpers\GroupBindings\GroupBindingAccessor;
use App\Helpers\PermissionHints;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Group;
use App\Model\Entity\GroupExamLock;
use App\Model\Entity\GroupExternalAttribute;
use App\Model\Entity\GroupMembership;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentPoints;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\ShadowAssignmentPointsRepository;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IShadowAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;
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
     *                                   This variable allows us bulk-load optimizations for the solutions.
     * @return array Students statistics
     */
    private function getStudentStatsInternal(
        Group $group,
        User $student,
        array $assignmentSolutions,
        array $reviewRequests
    ) {
        $maxPoints = $group->getMaxPoints();
        $shadowPointsMap = $this->shadowAssignmentPointsRepository->findPointsForAssignments(
            $group->getShadowAssignments()->getValues(),
            $student
        );
        $gainedPoints = $this->getPointsGainedByStudentForSolutions($assignmentSolutions);
        $gainedPoints += $this->getPointsForShadowAssignments($shadowPointsMap);

        $studentReviewRequests = $reviewRequests[$student->getId()] ?? [];

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
                "accepted" => $best ? $best->isAccepted() : null,
                "reviewRequest" => !empty($studentReviewRequests[$assignment->getId()])
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

        $passesLimit = null;  // null = no limit
        $limit = null;
        if ($group->getPointsLimit() !== null && $group->getPointsLimit() > 0) {
            $limit = $group->getPointsLimit();
            $passesLimit = $gainedPoints >= $limit;
        } elseif ($group->getThreshold() !== null && $group->getThreshold() > 0) {
            $limit = $maxPoints * $group->getThreshold();
            $passesLimit = $gainedPoints >= $limit;
        }
        return [
            "userId" => $student->getId(),
            "groupId" => $group->getId(),
            "points" => [
                "total" => $maxPoints,
                "limit" => $limit,
                "gained" => $gainedPoints,
            ],
            "hasLimit" => $passesLimit !== null,
            "passesLimit" => $passesLimit ?? true,
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
        $reviewRequestSolutions = $this->assignmentSolutions->findReviewRequestSolutionsIndexed($group, $student);
        return $this->getStudentStatsInternal($group, $student, $assignmentSolutions, $reviewRequestSolutions);
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
        $reviewRequestSolutions = $this->assignmentSolutions->findReviewRequestSolutionsIndexed($group);
        return array_map(
            function ($student) use ($group, $assignmentSolutions, $reviewRequestSolutions) {
                $solutions = array_key_exists(
                    $student->getId(),
                    $assignmentSolutions
                ) ? $assignmentSolutions[$student->getId()] : [];
                return $this->getStudentStatsInternal($group, $student, $solutions, $reviewRequestSolutions);
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
                "supervisors" => $group->getSupervisorsIds(),
                "observers" => $group->getObserversIds(),
                "students" => $this->groupAcl->canViewStudents($group) ? $group->getStudentsIds() : [],
                "instanceId" => $group->getInstance() ? $group->getInstance()->getId() : null,
                "hasValidLicence" => $group->hasValidLicense(),
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
                "detaining" => $group->isDetaining(),
                "threshold" => $group->getThreshold(),
                "pointsLimit" => $group->getPointsLimit(),
                "bindings" => $this->bindings->getBindingsForGroup($group),
                "examBegin" => $group->hasExamPeriodSet() ? $group->getExamBegin()?->getTimestamp() : null,
                "examEnd" => $group->hasExamPeriodSet() ? $group->getExamEnd()?->getTimestamp() : null,
                "examLockStrict" => $group->hasExamPeriodSet() ? $group->isExamLockStrict() : null,
                "exams" => $group->getExams()->getValues(),
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
            "exam" => $group->isExam(),
            "archived" => $group->isArchived(),
            "public" => $group->isPublic(),
            "directlyArchived" => $group->isDirectlyArchived(),
            "localizedTexts" => $group->getLocalizedTexts()->getValues(),
            "primaryAdminsIds" => $group->getPrimaryAdminsIds(),
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

    /**
     * Preprocess an array of exam locks based on group ACLs.
     * @param Group $group
     * @param GroupExamLock[] $locks
     * @return array
     */
    public function getGroupExamLocks(Group $group, array $locks): array
    {
        $ips = $this->groupAcl->canViewExamLocksIPs($group);
        return array_map(function ($lock) use ($ips) {
            $res = $lock->jsonSerialize();
            if (!$ips) {
                unset($res['remoteAddr']);
            }
            return $res;
        }, $locks);
    }

    /**
     * Get a subset of group data relevant (available) for ReCodEx extensions (plugins).
     * @param Group $group to be rendered
     * @param array $injectAttributes [service][key] => value
     * @param string|null $membershipType GroupMembership::TYPE_* value for user who made the search
     * @return array
     */
    public function getGroupForExtension(
        Group $group,
        array $injectAttributes = [],
        ?string $membershipType = null,
        array $adminUsers = [],
    ): array {
        $admins = [];
        foreach ($group->getPrimaryAdminsIds() as $id) {
            $admins[$id] = null;
            if (array_key_exists($id, $adminUsers)) {
                $admins[$id] = $adminUsers[$id]->getNameParts(); // an array with name and title components
                $admins[$id]['email'] = $adminUsers[$id]->getEmail();
            }
        }
        return [
            "id" => $group->getId(),
            "parentGroupId" => $group->getParentGroup() ? $group->getParentGroup()->getId() : null,
            "admins" => $admins,
            "localizedTexts" => $group->getLocalizedTexts()->getValues(),
            "organizational" => $group->isOrganizational(),
            "exam" => $group->isExam(),
            "public" => $group->isPublic(),
            "detaining" => $group->isDetaining(),
            "attributes" => $injectAttributes,
            "membership" => $membershipType,
        ];
    }

    /**
     * Get a subset of groups data relevant (available) for ReCodEx extensions (plugins).
     * @param Group[] $groups
     * @param GroupExternalAttribute[] $attributes
     * @param GroupMembership[] $memberships GroupMembership[] to filter by
     */
    public function getGroupsForExtension(array $groups, array $attributes = [], array $memberships = []): array
    {
        // create a multi-dimensional map [groupId][attr-service][attr-key] => attr-value
        $attributesMap = [];
        foreach ($attributes as $attribute) {
            $gid = $attribute->getGroup()->getId();
            $attributesMap[$gid] = $attributesMap[$gid] ?? [];

            $service = $attribute->getService();
            $attributesMap[$gid][$service] = $attributesMap[$gid][$service] ?? [];

            $key = $attribute->getKey();
            $attributesMap[][$service][$key] = $attribute->getValue();
        }

        // create membership mapping group-id => membership-type
        $membershipsMap = [];
        foreach ($memberships as $membership) {
            $gid = $membership->getGroup()->getId();
            $membershipsMap[$gid] = $membership->getType();
        }

        $admins = [];
        foreach ($groups as $group) {
            foreach ($group->getPrimaryAdmins() as $admin) {
                $admins[$admin->getId()] = $admin;
            }
        }

        return array_map(
            function (Group $group) use ($attributesMap, $membershipsMap, $admins) {
                $id = $group->getId();
                return $this->getGroupForExtension(
                    $group,
                    $attributesMap[$id] ?? [],
                    $membershipsMap[$id] ?? null,
                    $admins
                );
            },
            $groups
        );
    }
}
