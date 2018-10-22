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


/**
 * Factory for group views which somehow do not fit into json serialization of
 * entities.
 */
class GroupViewFactory {

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

  public function __construct(AssignmentSolutions $assignmentSolutions,
                              IGroupPermissions $groupAcl,
                              IAssignmentPermissions $assignmentAcl,
                              IShadowAssignmentPermissions $shadowAssignmentAcl,
                              GroupBindingAccessor $bindings,
                              ShadowAssignmentPointsRepository $shadowAssignmentPointsRepository) {
    $this->assignmentSolutions = $assignmentSolutions;
    $this->groupAcl = $groupAcl;
    $this->assignmentAcl = $assignmentAcl;
    $this->shadowAssignmentAcl = $shadowAssignmentAcl;
    $this->bindings = $bindings;
    $this->shadowAssignmentPointsRepository = $shadowAssignmentPointsRepository;
  }


  /**
   * Get total sum of points which given user gained in given solutions.
   * @param Pair[] $solutions list of pairs, second item is solution, can be null
   * @return int
   */
  private function getPointsGainedByStudentForSolutions(array $solutions) {
    return array_reduce(
      $solutions,
      function ($carry, Pair $solutionPair) {
        $best = $solutionPair->value;
        if ($best !== null) {
          $carry += $best->getTotalPoints();
        }

        return $carry;
      },
      0
    );
  }

  /**
   * Get total sum of points which given user gained in given shadow assignments.
   * @param ShadowAssignmentPoints[] $shadowPointsList
   * @return int
   */
  private function getPointsForShadowAssignments(array $shadowPointsList): int {
    return array_reduce($shadowPointsList,
      function ($carry, ShadowAssignmentPoints $points) {
        return $carry + $points->getPoints();
      },
      0);
  }

  /**
   * Get the statistics of an individual student.
   * @param Group $group
   * @param User $student Student of this group
   * @return array Students statistics
   */
  public function getStudentsStats(Group $group, User $student) {
    $maxPoints = $group->getMaxPoints();
    $assignmentSolutions = $this->assignmentSolutions->findBestSolutionsForAssignments($group->getAssignments()->getValues(), $student);
    $shadowPointsList = $this->shadowAssignmentPointsRepository->findPointsForAssignments($group->getShadowAssignments()->getValues(), $student);
    $gainedPoints = $this->getPointsGainedByStudentForSolutions($assignmentSolutions);
    $gainedPoints += $this->getPointsForShadowAssignments($shadowPointsList);

    $assignments = [];
    foreach ($assignmentSolutions as $solutionPair) {
      /**
       * @var Assignment $assignment
       * @var AssignmentSolution $best
       */
      $assignment = $solutionPair->key;
      $best = $solutionPair->value;
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

    $shadowAssignments = [];
    foreach ($shadowPointsList as $shadowPoints) {
      $assignment = $shadowPoints->getShadowAssignment();
      $shadowAssignments[] = [
        "id" => $assignment->getId(),
        "points" => [
          "total" => $assignment->getMaxPoints(),
          "gained" => $shadowPoints->getPoints()
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
      "assignments" => $assignments,
      "shadowAssignments" => $shadowAssignments
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
        "admins" => $group->getAdminsIds(),
        "supervisors" => $group->getSupervisors()->map(function(User $s) { return $s->getId(); })->getValues(),
        "students" => $group->getStudents()->map(function(User $s) { return $s->getId(); })->getValues(),
        "instanceId" => $group->getInstance() ? $group->getInstance()->getId() : null,
        "hasValidLicence" => $group->hasValidLicence(),
        "assignments" => $group->getAssignments()->filter(function (Assignment $assignment) {
            return $this->assignmentAcl->canViewDetail($assignment);
          })->map(function (Assignment $assignment) {
            return $assignment->getId();
          })->getValues(),
        "shadowAssignments" => $group->getShadowAssignments()->filter(function (ShadowAssignment $assignment) {
            return $this->shadowAssignmentAcl->canViewDetail($assignment);
          })->map(function (ShadowAssignment $assignment) {
            return $assignment->getId();
          })->getValues(),
        "publicStats" => $group->getPublicStats(),
        "threshold" => $group->getThreshold(),
        "bindings" => $this->bindings->getBindingsForGroup($group)
      ];
    }

    $childGroups = $group->getChildGroups()->filter(function (Group $group) use ($ignoreArchived) {
      return !($ignoreArchived && $group->isArchived()) && $this->groupAcl->canViewPublicDetail($group);
    });

    return [
      "id" => $group->getId(),
      "externalId" => $group->getExternalId(),
      "organizational" => $group->isOrganizational(),
      "archived" => $group->isArchived(),
      "public" => $group->isPublic(),
      "directlyArchived" => $group->isDirectlyArchived(),
      "localizedTexts" => $group->getLocalizedTexts()->getValues(),
      "primaryAdminsIds" => $group->getPrimaryAdmins()->map(function (User $user) {
        return $user->getId();
      })->getValues(),
      "parentGroupId" => $group->getParentGroup() ? $group->getParentGroup()->getId() : null,
      "parentGroupsIds" => $group->getParentGroupsIds(),
      "childGroups" => $childGroups->map(function (Group $group) { return $group->getId(); })->getValues(),
      "privateData" => $privateData,
      "permissionHints" => PermissionHints::get($this->groupAcl, $group)
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
