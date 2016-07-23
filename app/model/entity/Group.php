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
     * @ORM\ManyToMany(targetEntity="User", mappedBy="studentOf")
     */
    protected $students;
    
    public function getStudents() {
        return $this->students;
    }
    
    /**
     * @ORM\ManyToMany(targetEntity="User", mappedBy="supervisorOf")
     */
    protected $supervisors;
    
    public function getSupervisors() {
        return $this->supervisors;
    }
    
    /**
     * @ORM\OneToMany(targetEntity="ExerciseAssignment", mappedBy="group")
     */
    protected $assignments;
    
    public function getAssignments() {
        return $this->assignments;
    }

    public function jsonSerialize() {
      return [
        'id' => $this->id,
        'name' => $this->name,
        'supervisors' => $this->supervisors->toArray()
      ];
    }

    public static function createGroup($name) {
        $group = new Group;
        $group->name = $name;
        $group->members = new ArrayCollection;
        return $group;
    }
}
