<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class User implements JsonSerializable
{
    use \Kdyby\Doctrine\Entities\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    public function getId() {
      return $this->id;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $degreesBeforeName;

    /**
     * @ORM\Column(type="string")
     */
    protected $firstName;

    /**
     * @ORM\Column(type="string")
     */
    protected $lastName;

    /**
     * @ORM\Column(type="string")
     */
    protected $degreesAfterName;

    /**
     * @ORM\Column(type="string")
     */
    protected $email;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isVerified;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isAllowed;

    public function isAllowed() { return $this->isAllowed; }

    /**
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="students")
     * @ORM\JoinTable(name="group_student")
     */
    protected $studentOf;

    public function getGroupsAsStudent() {
        return $this->studentOf;
    }

    /**
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="supervisors")
     * @ORM\JoinTable(name="group_supervisor")
     */
    protected $supervisorOf;

    public function getGroupsAsSupervisor() {
        return $this->supervisorOf;
    }
    
    /**
     * @ORM\OneToMany(targetEntity="Exercise", mappedBy="author")
     */
    protected $exercises;
    
    public function getUsersExercises() {
      return $this->exercises;
    }

    /**
     * @ORM\ManyToOne(targetEntity="Role")
     */
    protected $role;

    public function getRole() { return $this->role; }

    public function jsonSerialize() {
      return [
        'id' => $this->id,
        'fullName' => trim("{$this->degreesBeforeName} {$this->firstName} {$this->lastName} {$this->degreesAfterName}"),
        'name' => [
          'degreesBeforeName' => $this->degreesBeforeName,
          'firstName' => $this->firstName,
          'lastName' => $this->lastName,
          'degreesAfterName' => $this->degreesAfterName,
        ],
        'email' => $this->email,
        'isVerified' => $this->isVerified,
        'role' => $this->role
      ];
    }
  
    /**
     * The name of the user
     * @param  string $name   Name of the user
     * @param  Avatar $avatar User's avatar of choice
     * @return User
     */
    public static function createAnonymousUser($name) {
        $user = new User;
        $user->name = $name;
        $user->email = NULL;
        $user->isVerified = FALSE;
        $user->groups = new ArrayCollection;
        $user->exercises = new ArrayCollection;
        return $user;
    }
}
