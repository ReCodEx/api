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

  public function __construct(string $name, string $description, Instance $instance, User $admin, Group $parentGroup = NULL, bool $publicStats = TRUE) {
    $this->name = $name;
    $this->description = $description;
    $this->memberships = new ArrayCollection;
    $this->admin = $admin;
    $this->instance = $instance;
    $this->publicStats = $publicStats;
    $this->childGroups = new ArrayCollection;
    $this->assignments = new ArrayCollection;
    $admin->makeSupervisorOf($this);
    $this->parentGroup = $parentGroup;
    if ($parentGroup !== NULL) {
      $this->parentGroup->addChildGroup($this);
    }
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
   * @ORM\Column(type="string")
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

  protected function getActiveMemberships(string $type) {
    $filter = Criteria::create()
                ->where(Criteria::expr()->eq("type", $type))
                ->andWhere(Criteria::expr()->eq("status", GroupMembership::STATUS_ACTIVE));

    return $this->memberships->matching($filter);
  }

  protected function getActiveMembers(string $type) {
    return $this->getActiveMemberships($type)->map(
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
    // @todo: this could be more effective
    return $this->isStudentOf($user) || $this->isSupervisorOf($user) || $this->isAdminOf($user);
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
    $admins = $this->getAdminsIds();
    return array_search($user->getId(), $admins, TRUE) !== FALSE;
  }

  public function makeAdmin(User $user) {
    $this->admin = $user;
  }

  /**
   * @ORM\OneToMany(targetEntity="ExerciseAssignment", mappedBy="group")
   */
  protected $assignments;

  public function getAssignmentsIds(): array {
    return $this->getAssignments()->map(function($a) { return $a->id; })->toArray();
  }

  public function getMaxPoints(): int {
    return array_reduce(
      $this->getAssignments()->toArray(),
      function ($carry, $assignment) { return $carry + $assignment->getMaxPoints(); },
      0
    );
  }

  public function getBestSolutions(User $user): array {
    return $this->assignments->map(
      function ($assignment) use ($user) {
        return $assignment->getBestSolution($user);
      }
    )->toArray();
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
      $this->getCompletedAssignmentsByStudent($student)->toArray(),
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

  public function jsonSerialize() {
    $instance = $this->getInstance();
    $admin = $this->admin;
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description,
      "adminId" => $admin ? $this->admin->getId() : NULL,
      "admins" => $this->getAdminsIds(),
      "supervisors" => $this->getSupervisors()->map(function($s) { return $s->getId(); })->toArray(),
      "students" => $this->getStudents()->map(function($s) { return $s->getId(); })->toArray(),
      "instanceId" => $instance ? $instance->getId() : NULL,
      "hasValidLicence" => $this->hasValidLicence(),
      "parentGroupId" => $this->parentGroup ? $this->parentGroup->getId() : NULL,
      "childGroups" => $this->childGroups->map(function($group) { return $group->getId(); })->toArray(),
      "assignments" => $this->getAssignmentsIds(),
      "publicStats" => $this->publicStats
    ];
  }

}
