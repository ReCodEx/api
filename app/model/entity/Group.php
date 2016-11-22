<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(name="`group`")
 */
class Group implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
      string $name,
      string $description,
      Instance $instance,
      User $admin,
      Group $parentGroup = NULL,
      bool $publicStats = TRUE,
      bool $isPublic = TRUE) {
    $this->name = $name;
    $this->description = $description;
    $this->memberships = new ArrayCollection;
    $this->admin = $admin;
    $this->instance = $instance;
    $this->publicStats = $publicStats;
    $this->isPublic = $isPublic;
    $this->childGroups = new ArrayCollection;
    $this->assignments = new ArrayCollection;
    $admin->makeSupervisorOf($this);
    $this->parentGroup = $parentGroup;
    if ($parentGroup !== NULL) {
      $this->parentGroup->addChildGroup($this);
    }

    $instance->addGroup($this);
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\Column(type="float", nullable=true)
   */
  protected $threshold;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $publicStats;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  public function isPublic(): bool {
    return $this->isPublic;
  }

  public function isPrivate(): bool {
    return !$this->isPublic;
  }

  public function statsArePublic(): bool {
    return $this->publicStats;
  }

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="childGroups")
   */
  protected $parentGroup;

  /**
   * @ORM\OneToMany(targetEntity="Group", mappedBy="parentGroup")
   */
  protected $childGroups;

  /**
   * Recursivelly merge all the subgroups into a flat array of groups.
   * @return array
   */
  public function getAllSubgroups() {
    $subtrees = $this->childGroups->map(function ($group) {
      return $group->getAllSubgroups();
    });
    return array_merge($this->childGroups->getValues(), ...$subtrees);
  }

  /**
   * @ORM\ManyToOne(targetEntity="Instance", inversedBy="groups")
   */
  protected $instance;

  public function getInstance() {
    $group = $this;
    do {
      if ($group->instance) {
        return $group->instance;
      }
      $group = $group->parentGroup;
    } while ($group);

    return NULL;
  }

  public function hasValidLicence() {
    $instance = $this->getInstance();
    return $instance && $instance->hasValidLicence();
  }

  /**
   * @ORM\OneToMany(targetEntity="GroupMembership", mappedBy="group", cascade={"all"})
   */
  protected $memberships;

  public function addMembership(GroupMembership $membership) {
    $this->memberships->add($membership);
  }

  public function removeMembership(GroupMembership $membership) {
    $this->memberships->remove($membership);
  }

  protected function getActiveMemberships() {
    $filter = Criteria::create()
                ->where(Criteria::expr()->eq("status", GroupMembership::STATUS_ACTIVE));

    return $this->memberships->matching($filter);
  }

  /**
   * Return all active members depending on specified type
   */
  protected function getActiveMembers(string $type) {
    if ($type == GroupMembership::TYPE_ALL) {
      $members = $this->getActiveMemberships();
    } else {
      $filter = Criteria::create()->where(Criteria::expr()->eq("type", $type));
      $members = $this->getActiveMemberships()->matching($filter);
    }

    return $members->map(
      function(GroupMembership $membership) {
        return $membership->getUser();
      }
    );
  }

  public function getStudents() {
    return $this->getActiveMembers(GroupMembership::TYPE_STUDENT);
  }

  public function isStudentOf(User $user) {
    return $this->getStudents()->contains($user);
  }

  public function getSupervisors() {
    return $this->getActiveMembers(GroupMembership::TYPE_SUPERVISOR);
  }

  public function isSupervisorOf(User $user) {
    return $this->getSupervisors()->contains($user);
  }

  public function isMemberOf(User $user) {
    return $this->getActiveMembers(GroupMembership::TYPE_ALL)->contains($user);
  }

  /**
   * Can given user access information about this group.
   * @param User $user
   * @return bool true if user can access group
   */
  public function canUserAccessGroupDetail(User $user) {
    if ($this->isMemberOf($user)
        || $user->getRole()->isSuperadmin()
        || $this->isPublic === TRUE) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $admin;

  public function getAdminsIds() {
    $group = $this;
    $admins = [];
    while ($group !== NULL) {
      if ($group->admin !== NULL) {
        $admins[] = $group->admin->getId();
      }

      $group = $group->parentGroup;
    }

    return array_unique($admins);
  }

  /**
   * User is admin of a group when he is
   * @param User $user
   * @return bool
   */
  public function isAdminOf(User $user) {
    if (!$user->getRole()->hasLimitedRights()) {
      return TRUE;
    }

    $admins = $this->getAdminsIds();
    return array_search($user->getId(), $admins, TRUE) !== FALSE;
  }

  public function makeAdmin(User $user) {
    $this->admin = $user;
  }

  /**
   * @ORM\OneToMany(targetEntity="Assignment", mappedBy="group")
   */
  protected $assignments;

  /**
   * Map collection of assignments to an array of its ID's
   * @param ArrayCollection   $assignments  List of assignments
   * @return string[]
   */
  public function getAssignmentsIds($assignments = NULL): array {
    $assignments = $assignments === NULL ? $this->assignments : $assignments;
    return $assignments->map(function($a) { return $a->id; })->getValues();
  }

  public function getMaxPoints(): int {
    return array_reduce(
      $this->getAssignments()->getValues(),
      function ($carry, $assignment) { return $carry + $assignment->getMaxPoints(); },
      0
    );
  }

  public function getBestSolutions(User $user): array {
    return $this->assignments->map(
      function ($assignment) use ($user) {
        return $assignment->getBestSolution($user);
      }
    )->getValues();
  }

  public function getCompletedAssignmentsByStudent(User $student) {
    return $this->getAssignments()->filter(
      function($assignment) use ($student) {
        return $assignment->getBestSolution($student) !== NULL;
      }
    );
  }

  public function getMissedAssignmentsByStudent(User $student) {
    return $this->getAssignments()->filter(
      function($assignment) use ($student) {
        return $assignment->isAfterDeadline() && $assignment->getBestSolution($student) === NULL;
      }
    );
  }

  public function getPointsGainedByStudent(User $student) {
    return array_reduce(
      $this->getCompletedAssignmentsByStudent($student)->getValues(),
      function ($carry, $assignment) use ($student) {
        $best = $assignment->getBestSolution($student);
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
   * @param User $student   Student of this group
   * @return array          Students statistics
   */
  public function getStudentsStats(User $student) {
    $total = $this->assignments->count();
    $completed = $this->getCompletedAssignmentsByStudent($student);
    $missed = $this->getMissedAssignmentsByStudent($student);
    $maxPoints = $this->getMaxPoints();
    $gainedPoints = $this->getPointsGainedByStudent($student);

    $statuses = [];
    foreach ($this->assignments as $assignment) {
      $best = $assignment->getBestSolution($student);
      $solution = $best ? $best : $assignment->getLastSolution($student);
      $statuses[$assignment->getId()] = $solution ? $solution->getEvaluationStatus() : NULL;
    }

    return [
      "userId" => $student->getId(),
      "groupId" => $this->getId(),
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
      "hasLimit" => $this->threshold !== NULL && $this->threshold > 0,
      "passesLimit" => $this->threshold === NULL ? TRUE : $gainedPoints >= $maxPoints * $this->threshold
    ];
  }

  /**
   * Get all possible assignment for user based on his/hers role.
   * @param User $user
   * @return ArrayCollection list of assignments
   */
  public function getAssignmentsForUser(User $user) {
    if ($this->isAdminOf($user) || $this->isSupervisorOf($user)) {
      return $this->assignments;
    } else if ($this->isStudentOf($user)) {
      return $this->getPublicAssignments();
    } else {
      return new ArrayCollection;
    }
  }

  /**
   * Student can view only public assignments.
   */
  public function getPublicAssignments() {
    return $this->getAssignments()->filter(
      function ($assignment) { return $assignment->isPublic(); }
    );
  }

  public function jsonSerialize() {
    $instance = $this->getInstance();
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description,
      "adminId" => $this->admin ? $this->admin->getId() : NULL,
      "admins" => $this->getAdminsIds(),
      "supervisors" => $this->getSupervisors()->map(function($s) { return $s->getId(); })->getValues(),
      "students" => $this->getStudents()->map(function($s) { return $s->getId(); })->getValues(),
      "instanceId" => $instance ? $instance->getId() : NULL,
      "hasValidLicence" => $this->hasValidLicence(),
      "parentGroupId" => $this->parentGroup ? $this->parentGroup->getId() : NULL,
      "childGroups" => [
        "all" => $this->childGroups->map(
          function($group) {
            return $group->getId();
          }
        )->getValues(),
        "public" => $this->childGroups->filter(
          function($g) {
            return $g->isPublic();
          }
        )->map(
          function($group) {
            return $group->getId();
          }
        )->getValues()
      ],
      "assignments" => [
        "all" => $this->getAssignmentsIds(),
        "public" => $this->getAssignmentsIds($this->getPublicAssignments())
      ],
      "publicStats" => $this->publicStats,
      "isPublic" => $this->isPublic,
      "threshold" => $this->threshold
    ];
  }
}
