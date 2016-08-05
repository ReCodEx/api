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

  public function getId() { return $this->id; }
  
  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  public function getName() { return $this->name; }

  /**
   * @ORM\Column(type="string")
   */
  protected $description;

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
  
  public function getStudents() {
    return $this->students;
  }

  public function isStudentOf(User $user) {
    return $this->students->contains($user);
  }
  
  /**
   * @ORM\ManyToMany(targetEntity="User", mappedBy="supervisorOf")
   */
  protected $supervisors;
  
  public function getSupervisors() {
    return $this->supervisors;
  }

  public function isSupervisorOf(User $user) {
    return $this->supervisors->contains($user);
  }

  public function isMemberOf(User $user) {
    return $this->isStudentOf($user) || $this->isSupervisorOf($user);
  }
  
  /**
   * @ORM\OneToMany(targetEntity="ExerciseAssignment", mappedBy="group")
   */
  protected $assignments;
  
  public function getAssignments() {
    return $this->assignments;
  }

  public function jsonSerialize() {
    $instance = $this->getInstance();
    return [
      'id' => $this->id,
      'name' => $this->name,
      'description' => $this->description,
      'supervisors' => $this->supervisors->toArray(),
      'students' => $this->students->toArray(),
      'hasValidLicence' => $this->hasValidLicence(),
      'instanceId' => $instance ? $instance->getId() : NULL,
      'parentGroupId' => $this->parentGroup ? $this->parentGroup->getId() : NULL,
      'childGroups' => $this->childGroups->toArray(),
      'assignments' => array_map(function ($assignment) { return $assignment->getId(); }, $this->getAssignments()->toArray())
    ];
  }

  public static function createGroup($name) {
    $group = new Group;
    $group->name = $name;
    $group->members = new ArrayCollection;
    return $group;
  }
}
