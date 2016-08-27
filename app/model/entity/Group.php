<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(name="`group`")
 */
class Group implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;
  
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
   * @ORM\ManyToMany(targetEntity="User", mappedBy="studentOf")
   */
  protected $students;

  public function isStudentOf(User $user) {
    return $this->students->contains($user);
  }

  public function addStudent(User $user) {
    $this->students->add($user);
  }

  public function removeStudent(User $user) {
    $this->students->removeElement($user);
  }

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $admin;

  /**
   * @ORM\ManyToMany(targetEntity="User", mappedBy="supervisorOf")
   */
  protected $supervisors;

  public function isSupervisorOf(User $user) {
    return $this->supervisors->contains($user);
  }

  public function addSupervisor(User $user) {
    $this->supervisors->add($user);
  }

  public function removeSupervisor(User $user) {
    $this->supervisors->removeElement($user);
  }

  public function isMemberOf(User $user) {
    return $this->isStudentOf($user) || $this->isSupervisorOf($user);
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

  public function getCompletedAssignmentsByStudent(User $student) {
    return $this->getAssignments()->filter(
      function($assignment) use ($student) {
        return $assignment->getBestSolution($student) !== NULL;
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
    $completed = $this->getCompletedAssignmentsByStudent($student)->count();
    $maxPoints = $this->getMaxPoints();
    $gainedPoints = $this->getPointsGainedByStudent($student);

    return [
      "assignments" => [
        "total" => $total,
        "completed" => $completed
      ],
      "points" => [
        "total" => $maxPoints,
        "gained" => $gainedPoints
      ],
      "hasLimit" => $this->threshold !== NULL && $this->threshold > 0,
      "passesLimit" => $this->threshold === NULL ? TRUE : $gainedPoints >= $maxPoints * $this->threshold 
    ];
  }

  public function jsonSerialize() {
    $instance = $this->getInstance();
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description,
      "supervisors" => $this->supervisors->toArray(),
      "students" => $this->students->toArray(), // @todo: Only IDs
      "hasValidLicence" => $this->hasValidLicence(),
      "instanceId" => $instance ? $instance->getId() : NULL,
      "parentGroupId" => $this->parentGroup ? $this->parentGroup->getId() : NULL,
      "childGroups" => array_map(function($group) { return $group->getId(); }, $this->childGroups->toArray()),
      "assignments" => $this->getAssignmentsIds()
    ];
  }

  public static function createGroup($name) {
    $group = new Group;
    $group->name = $name;
    $group->members = new ArrayCollection;
    return $group;
  }
}
