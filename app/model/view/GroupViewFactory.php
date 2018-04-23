<?php

namespace App\Model\View;

use App\Helpers\EvaluationStatus\EvaluationStatus;
use App\Helpers\Localizations;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Group;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use App\Security\ACL\IGroupPermissions;
use Doctrine\Common\Collections\Collection;


/**
 * Factory for group views which somehow do not fit into json serialization of
 * entities.
 */
class GroupViewFactory {

  /**
   * @var AssignmentSolutions
   */
  private $assignmentSolutions;

  /**
   * @var IGroupPermissions
   */
  private $groupAcl;

  public function __construct(AssignmentSolutions $assignmentSolutions, IGroupPermissions $groupAcl) {
    $this->assignmentSolutions = $assignmentSolutions;
    $this->groupAcl = $groupAcl;
  }


  /**
   * Get total sum of points which given user gained in given solutions.
   * @param array $solutions list of pairs, second item is solution, can be null
   * @return int
   */
  private function getPointsGainedByStudentForSolutions(array $solutions) {
    return array_reduce(
      $solutions,
      function ($carry, array $solutionPair) {
        $best = $solutionPair[1];
        if ($solutionPair[1] !== null) {
          $carry += $best->getTotalPoints();
        }

        return $carry;
      },
      0
    );
  }

  /**
   * Get the statistics of an individual student.
   * @param Group $group
   * @param User $student Student of this group
   * @return array Students statistics
   */
  public function getStudentsStats(Group $group, User $student) {
    $maxPoints = $group->getMaxPoints();
    $solutions = $this->assignmentSolutions->findBestSolutionsForAssignments($group->getAssignments()->getValues(), $student);
    $gainedPoints = $this->getPointsGainedByStudentForSolutions($solutions);

    $assignments = [];
    foreach ($solutions as $solutionPair) {
      /**
       * @var Assignment $assignment
       * @var AssignmentSolution $best
       */
      $assignment = $solutionPair[0];
      $best = $solutionPair[1];
      $submission = $best ? $best->getLastSubmission() : null;

      $assignments[] = [
        "id" => $assignment->getId(),
        "status" => $submission ? EvaluationStatus::getStatus($submission) : null,
        "points" => [
          "total" => $assignment->getMaxPointsBeforeFirstDeadline(),
          "gained" => $best ? $best->getPoints() : null,
          "bonus" => $best ? $best->getBonusPoints() : null
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
      "passesLimit" => $group->getThreshold() === null ? true : $gainedPoints >= $maxPoints * $group->getThreshold(),
      "assignments" => $assignments
    ];
  }

  /**
   * Get as much group detail info as your permissions grants you.
   * @param Group $group
   * @return array
   */
  public function getGroup(Group $group, bool $ignoreArchived = true): array {
    $canView = $this->groupAcl->canViewDetail($group);
    /** @var LocalizedGroup $primaryLocalization */
    $primaryLocalization = Localizations::getPrimaryLocalization($group->getLocalizedTexts());

    $privateData = null;
    if ($canView) {
      $privateData = [
        "description" => $primaryLocalization ? $primaryLocalization->getDescription() : "", # BC
        "admins" => $group->getAdminsIds(),
        "supervisors" => $group->getSupervisors()->map(function(User $s) { return $s->getId(); })->getValues(),
        "students" => $group->getStudents()->map(function(User $s) { return $s->getId(); })->getValues(),
        "instanceId" => $group->getInstance() ? $group->getInstance()->getId() : null,
        "hasValidLicence" => $group->hasValidLicence(),
        "assignments" => [
          "all" => $group->getAssignmentsIds(),
          "public" => $group->getAssignmentsIds($group->getPublicAssignments())
        ],
        "publicStats" => $group->getPublicStats(),
        "isPublic" => $group->isPublic(),
        "threshold" => $group->getThreshold()
      ];
    }

    $childGroups = $group->getChildGroups();
    $publicChildGroups = $group->getPublicChildGroups();

    if ($ignoreArchived) {
      $childGroups = $childGroups->filter(function (Group $group) {
        return !$group->isArchived();
      });

      $publicChildGroups = $publicChildGroups->filter(function (Group $group) {
        return !$group->isArchived();
      });
    }


    return [
      "id" => $group->getId(),
      "externalId" => $group->getExternalId(),
      "organizational" => $group->isOrganizational(),
      "archived" => $group->isArchived(),
      "directlyArchived" => $group->isDirectlyArchived(),
      "localizedTexts" => $group->getLocalizedTexts()->getValues(),
      "name" => $primaryLocalization ? $primaryLocalization->getName() : "", # BC
      "primaryAdminsIds" => $group->getPrimaryAdmins()->map(function (User $user) {
        return $user->getId();
      })->getValues(),
      "parentGroupId" => $group->getParentGroup() ? $group->getParentGroup()->getId() : null,
      "parentGroupsIds" => $group->getParentGroupsIds(),
      "childGroups" => [
        "all" => $childGroups->map(function (Group $group) { return $group->getId(); })->getValues(),
        "public" => $publicChildGroups->map(function (Group $group) { return $group->getId(); })->getValues()
      ],
      "canView" => $canView,
      "privateData" => $privateData
    ];
  }

  /**
   * Get group data which current user can view about given groups.
   * @param Group[] $groups
   * @return array
   */
  public function getGroups(array $groups, bool $ignoreArchived = true): array {
    return array_map(function (Group $group) use ($ignoreArchived) {
      return $this->getGroup($group, $ignoreArchived);
    }, $groups);
  }

}
