<?php

namespace App\Model\View;

use App\Helpers\EvaluationStatus\EvaluationStatus;
use App\Helpers\Localizations;
use App\Model\Entity\Assignment;
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
   * Get collection of completed assignments by given user.
   * @param Group $group
   * @param User $student
   * @return Collection
   */
  private function getCompletedAssignmentsByStudent(Group $group, User $student) {
    return $group->getAssignments()->filter(
      function(Assignment $assignment) use ($student) {
        return $this->assignmentSolutions->findBestSolution($assignment, $student) !== null;
      }
    );
  }

  /**
   * Get collection of assignments which given user do not submitted yet.
   * @param Group $group
   * @param User $student
   * @return Collection
   */
  private function getMissedAssignmentsByStudent(Group $group, User $student) {
    return $group->getAssignments()->filter(
      function(Assignment $assignment) use ($student) {
        $best = $this->assignmentSolutions->findBestSolution($assignment, $student);
        return $assignment->isAfterDeadline() && $best === null;
      }
    );
  }

  /**
   * Get total sum of points which given user gained in group.
   * @param Group $group
   * @param User $student
   * @return int
   */
  private function getPointsGainedByStudent(Group $group, User $student) {
    return array_reduce(
      $this->getCompletedAssignmentsByStudent($group, $student)->getValues(),
      function ($carry, Assignment $assignment) use ($student) {
        $best = $this->assignmentSolutions->findBestSolution($assignment, $student);
        if ($best !== NULL) {
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
    $total = $group->getAssignments()->count();
    $completed = $this->getCompletedAssignmentsByStudent($group, $student);
    $missed = $this->getMissedAssignmentsByStudent($group, $student);
    $maxPoints = $group->getMaxPoints();
    $gainedPoints = $this->getPointsGainedByStudent($group, $student);

    $statuses = [];
    /** @var Assignment $assignment */
    foreach ($group->getAssignments() as $assignment) {
      $best = $this->assignmentSolutions->findBestSolution($assignment, $student);
      $submission = $best ? $best->getLastSubmission() : null;
      $statuses[$assignment->getId()] = $submission ? EvaluationStatus::getStatus($submission) : null;
    }

    return [
      "userId" => $student->getId(),
      "groupId" => $group->getId(),
      "assignments" => [
        "total" => $total,
        "completed" => $completed->count(),
        "missed" => $missed->count()
      ],
      "points" => [
        "total" => $maxPoints,
        "gained" => $gainedPoints
      ],
      "statuses" => $statuses,
      "hasLimit" => $group->getThreshold() !== null && $group->getThreshold() > 0,
      "passesLimit" => $group->getThreshold() === null ? true : $gainedPoints >= $maxPoints * $group->getThreshold()
    ];
  }

  /**
   * Get as much group detail info as your permissions grants you.
   * @param Group $group
   * @return array
   */
  public function getGroup(Group $group): array {
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

    return [
      "id" => $group->getId(),
      "externalId" => $group->getExternalId(),
      "localizedTexts" => $group->getLocalizedTexts()->getValues(),
      "name" => $primaryLocalization ? $primaryLocalization->getName() : "", # BC
      "primaryAdminsIds" => $group->getPrimaryAdmins()->map(function (User $user) {
        return $user->getId();
      })->getValues(),
      "parentGroupId" => $group->getParentGroup() ? $group->getParentGroup()->getId() : null,
      "parentGroupsIds" => $group->getParentGroupsIds(),
      "childGroups" => [
        "all" => $group->getChildGroupsIds(),
        "public" => $group->getPublicChildGroupsIds()
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
  public function getGroups(array $groups): array {
    return array_map(function (Group $group) {
      return $this->getGroup($group);
    }, $groups);
  }

}
