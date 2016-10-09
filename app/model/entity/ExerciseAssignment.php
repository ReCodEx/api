<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use DateTime;
use App\Exceptions\MalformedJobConfigException;

/**
 * @ORM\Entity
 */
class ExerciseAssignment implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    string $name,
    string $description,
    DateTime $firstDeadline,
    int $maxPointsBeforeFirstDeadline,
    DateTime $secondDeadline,
    int $maxPointsBeforeSecondDeadline,
    Exercise $exercise,
    Group $group,
    bool $isPublic
  ) {
    $this->name = $name;
    $this->description = $description;
    $this->exercise = $exercise;
    $this->group = $group;
    $this->firstDeadline = $firstDeadline;
    $this->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
    $this->secondDeadline = $secondDeadline;
    $this->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
    $this->submissions = new ArrayCollection;
    $this->isPublic = $isPublic;
  }

  public static function assignToGroup(Exercise $exercies, Group $group, $isPublic = FALSE) {
    return new self(
      $exercise->name,
      $exercise->assignment,
      NULL,
      0,
      NULL,
      0,
      $exercise,
      $group,
      $isPublic
    );
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
    * @ORM\Column(type="boolean")
    */
  protected $isPublic;

  /**
    * @ORM\Column(type="smallint")
    */
  protected $submissionsCountLimit;

  /**
    * @ORM\Column(type="string")
    */
  protected $jobConfigFilePath;

  /**
   *
   * @return string File path of the 
   */
  public function getJobConfigFilePath(): string {
    if (!$this->jobConfigFilePath) {
      return $this->getExercise()->getJobConfigFilePath();
    }

    // @todo: Make dependable on the programming language/technology used by the user 
    return $this->jobConfigFilePath;
  }

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

  public function canReceiveSubmissions(User $user = NULL) {
    return $this->isPublic === TRUE &&
      $this->group->hasValidLicence() && 
      !$this->isAfterDeadline() &&
      ($user !== NULL && !$this->hasReachedSubmissionsCountLimit($user));
  }

  /**
    * Can a specific user access this assignment as student?
    */
  public function canAccessAsStudent(User $user) {
    return $this->isPublic === TRUE && $this->group->isStudentOf($user);
  }

  /**
    * Can a specific user access this assignment as supervisor?
    */
  public function canAccessAsSupervisor(User $user) {
    return $this->group->isSupervisorOf($user);
  }

  /**
   * @ORM\OneToMany(targetEntity="Submission", mappedBy="exerciseAssignment")
   * @ORM\OrderBy({ "submittedAt" = "DESC" })
   */
  protected $submissions;

  public function getValidSubmissions(User $user) {
    $fromThatUser = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("resultsUrl", NULL));
    $validSubmissions = function ($submission) {
      $evaluation = $submission->getEvaluation();
      // keep only solutions, which are marked as valid (both manual and automatic way)
      return ($evaluation->isValid() === TRUE && $evaluation->getEvaluationFailed() === FALSE);
    }; 
    
    return $this->submissions
      ->matching($fromThatUser)
      ->filter($validSubmissions)
      ->toArray();
  }

  public function hasReachedSubmissionsCountLimit(User $user) {
    return $this->getValidSubmissions($user)->count() >= $this->submissionsCountLimit;
  }

  public function getLastSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user));
    return $this->submissions->matching($usersSolutions)->first();
  }

  public function getBestSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("evaluation", NULL));

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
      "isPublic" => $this->isPublic,
      "description" => $this->getDescription(),
      "groupId" => $this->group->getId(),
      "deadline" => [
        "first" => $this->firstDeadline->getTimestamp(),
        "second" => $this->secondDeadline->getTimestamp()
      ],
      "submissionsCountLimit" => $this->submissionsCountLimit,
      "canReceiveSubmissions" => FALSE // the app must perform a special request to get the valid information
    ];
  }
}
