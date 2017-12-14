<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;

use App\Exceptions\ForbiddenRequestException;


/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getNote()
 * @method Solution getSolution()
 * @method Assignment getAssignment()
 * @method bool getAccepted()
 * @method setAccepted(bool $accepted)
 * @method int getBonusPoints()
 * @method setBonusPoints(int $points)
 * @method Collection getSubmissions()
 */
class AssignmentSolution implements JsonSerializable
{
  use MagicAccessors;

  const JOB_TYPE = "student";

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="text")
   */
  protected $note;

  /**
   * @var Assignment
   * @ORM\ManyToOne(targetEntity="Assignment")
   */
  protected $assignment;

  /**
   * Determine if submission was made after deadline.
   * @return bool
   */
  public function isAfterDeadline() {
    return $this->assignment->isAfterDeadline($this->solution->getCreatedAt());
  }

  public function getMaxPoints() {
    return $this->assignment->getMaxPoints($this->solution->getCreatedAt());
  }

  /**
   * Get actual points treshold in points.
   * @return int minimal points which submission has to gain
   */
  public function getPointsThreshold(): int {
    $threshold = $this->assignment->getPointsPercentualThreshold();
    return floor($this->getMaxPoints() * $threshold);
  }

  /**
   * @var Solution
   * @ORM\ManyToOne(targetEntity="Solution", cascade={"persist"})
   */
  protected $solution;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $accepted;

  public function isAccepted(): bool {
    return $this->accepted;
  }

  /**
   * @ORM\Column(type="integer")
   */
  protected $bonusPoints;

  /**
   * Get total points which this solution got. Including bonus points and points
   * for evaluation.
   * @return int
   */
  public function getTotalPoints() {
    $lastSubmission = $this->getLastSubmission();
    if ($lastSubmission === null) {
      return 0;
    }

    $evaluation = $lastSubmission->getEvaluation();
    if ($evaluation === null) {
      return 0;
    }

    return $evaluation->getPoints() + $this->bonusPoints;
  }

  /**
   * Note that order by annotation has to be present!
   *
   * @ORM\OneToMany(targetEntity="AssignmentSolutionSubmission", mappedBy="assignmentSolution")
   * @ORM\OrderBy({"submittedAt" = "DESC"})
   */
  protected $submissions;

  /**
   * Get last submission for this solution which is taken as the best one.
   * @return AssignmentSolutionSubmission|null
   */
  public function getLastSubmission(): ?AssignmentSolutionSubmission {
    $result = $this->submissions->first();
    return $result ? $result : null;
  }

  /**
   * @return string[]
   */
  public function getSubmissionsIds(): array {
    return $this->submissions->map(function (AssignmentSolutionSubmission $submission) {
      return $submission->getId();
    })->getValues();
  }

  /**
   * Parametrized view.
   * @param bool $canViewRatios
   * @param bool $canViewValues
   * @param bool $canViewResubmissions
   * @return array
   */
  public function getData($canViewRatios = false, bool $canViewValues = false, bool $canViewResubmissions = false) {
    $lastSubmissionId = $this->getLastSubmission() ? $this->getLastSubmission()->getId() : null;
    $lastSubmissionIdArray = $lastSubmissionId ? [ $lastSubmissionId ] : [];
    $submissions = $canViewResubmissions ? $this->getSubmissionsIds() : $lastSubmissionIdArray;

    return [
      "id" => $this->id,
      "note" => $this->note,
      "exerciseAssignmentId" => $this->assignment->getId(),
      "solution" => $this->solution,
      "runtimeEnvironmentId" => $this->solution->getRuntimeEnvironment()->getId(),
      "maxPoints" => $this->getMaxPoints(),
      "accepted" => $this->accepted,
      "bonusPoints" => $this->bonusPoints,
      "lastSubmission" => $this->getLastSubmission() ?  $this->getLastSubmission()->getData($canViewRatios, $canViewValues) : null,
      "submissions" => $submissions
    ];
  }

  /**
   * @return array
   */
  public function jsonSerialize() {
    return $this->getData($this->assignment->getCanViewLimitRatios());
  }

  /**
   * AssignmentSolution constructor.
   */
  private function __construct() {
    $this->accepted = false;
    $this->bonusPoints = 0;
    $this->submissions = new ArrayCollection;
  }

  /**
   * The name of the user
   * @param string $note
   * @param Assignment $assignment
   * @param Solution $solution
   * @return AssignmentSolution
   */
  public static function createSolution(
    string $note,
    Assignment $assignment,
    Solution $solution
  ) {
    $entity = new AssignmentSolution;
    $entity->assignment = $assignment;
    $entity->note = $note;
    $entity->solution = $solution;

    return $entity;
  }

}
