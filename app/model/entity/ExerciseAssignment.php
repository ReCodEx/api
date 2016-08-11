<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class ExerciseAssignment implements JsonSerializable
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
    * @ORM\Column(type="smallint")
    */
  protected $submissionsCountLimit;

  /**
    * @ORM\Column(type="string")
    */
  protected $jobConfigFilePath;

  /**
    * @ORM\Column(type="text")
    */
  protected $scoreConfig;

  /**
    * @ORM\Column(type="datetime")
    */
  protected $firstDeadline;

  /**
    * @ORM\Column(type="datetime")
    */
  protected $secondDeadline;

  public function isAfterDeadline() {
    return $this->secondDeadline < new \DateTime; 
  }

  /**
    * @ORM\Column(type="smallint")
    */
  protected $maxPointsBeforeFirstDeadline;

  /**
    * @ORM\Column(type="smallint")
    */
  protected $maxPointsBeforeSecondDeadline;

  public function getMaxPoints(DateTime $time = NULL) {
    if ($time === NULL || $time < $this->firstDeadline) {
      return $this->maxPointsBeforeFirstDeadline;
    } else if ($time < $this->secondDeadline) {
      return $this->maxPointsBeforeSecondDeadline;
    } else {
      return 0;
    }
  }

  /**
    * @ORM\Column(type="text")
    */
  protected $description;

  public function getDescription() {
    // @todo: this must be translatable

    $description = $this->description;
    $parent = $this->exercise;
    while (empty($description) && $parent !== NULL) {
      $description = $parent->description;
      $parent = $parent->exercise;
    }

    return $description;
  }

  /**
    * @ORM\ManyToOne(targetEntity="Exercise")
    * @ORM\JoinColumn(name="exercise_id", referencedColumnName="id")
    */
  protected $exercise;

  /**
    * @ORM\ManyToOne(targetEntity="Group", inversedBy="assignments")
    * @ORM\JoinColumn(name="group_id", referencedColumnName="id")
    */
  protected $group;

  public function canReceiveSubmissions() {
    // return $this->group->hasValidLicence(); // @todo WTF?!! $group->getInstance() always returns NULL...
    // @todo check the deadline
    return TRUE;
  }

  /**
    * Can a specific user access this assignment as student?
    */
  public function canAccessAsStudent(User $user) {
    return $this->group->isStudentOf($user);
  }

  /**
    * Can a specific user access this assignment as supervisor?
    */
  public function canAccessAsSupervisor(User $user) {
    return $this->group->isSupervisorOf($user);
  }

  /**
   * @ORM\OneToMany(targetEntity="Submission", mappedBy="exerciseAssignment")
   */
  protected $submissions;

  public function getBestSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->orWhere(Criteria::expr()->eq("user", $user))
      ->orWhere(Criteria::expr()->neq("evaluation", NULL));
    return array_reduce(
      $this->submissions->matching($usersSolutions)->toArray(),
      function ($best, $submission) {
        if ($best === NULL) {
          return $submission;
        }

        return $submission->getEvaluationStatus() !== "done" || $best->getTotalPoints() > $submission->getTotalPoints()
          ? $best
          : $submission;
      },
      NULL
    );
  }



  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->getDescription(),
      "groupId" => $this->group->getId(),
      "deadline" => [
        "first" => $this->firstDeadline->getTimestamp(),
        "second" => $this->secondDeadline->getTimestamp()
      ],
      "submissionsCountLimit" => $this->submissionsCountLimit,
      "canReceiveSubmissions" => $this->canReceiveSubmissions()
    ];
  }

  /**
    * The name of the user
    * @param  string $name   Name of the exercise
    * @return ExerciseAssignment
    */
  public static function createExerciseAssignment($name, $description, $firstDeadline, $maxPointsBeforeFirstDeadline, $secondDeadline, $maxPointsBeforeSecondDeadline, Exercise $exercise, Group $group) {
    $entity = new ExerciseAssignment;
    $entity->name = $name;
    $entity->description = $description;
    $entity->exercise = $exercise;
    $entity->group = $group;
    $entity->firstDeadline = $firstDeadline;
    $entity->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
    $entity->secondDeadline = $secondDeadline;
    $entity->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
    $entity->submissions = new ArrayCollection;
    return $entity;
  }
}
