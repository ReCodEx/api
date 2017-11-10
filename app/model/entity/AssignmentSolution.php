<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;

use App\Exceptions\ForbiddenRequestException;


/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getNote()
 * @method Solution getSolution()
 * @method Assignment getAssignment()
 * @method string getResultsUrl()
 * @method bool getAccepted()
 * @method setResultsUrl(string $url)
 * @method setAccepted(bool $accepted)
 * @method string getJobConfigPath()
 * @method \DateTime getSubmittedAt()
 * @method int getBonusPoints()
 * @method setBonusPoints(int $points)
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
    return $this->assignment->isAfterDeadline($this->submittedAt);
  }

  public function getMaxPoints() {
    return $this->assignment->getMaxPoints($this->submittedAt);
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
   * @ORM\OneToMany(targetEntity="AssignmentSolutionSubmission", mappedBy="assignmentSolution")
   */
  protected $submissions;

  public function getTotalPoints() {
    if ($this->evaluation === NULL) { // TODO: evaluation deleted
      return 0;
    }

    return $this->evaluation->getPoints() + $this->bonusPoints;
  }

  public function getData($canViewRatios = FALSE, bool $canViewValues = false) {
    $evaluation = $this->hasEvaluation() // TODO: hasEvaluation deleted
      ? $this->getEvaluation()->getData($canViewRatios, $canViewValues) // TODO: getEvaluation deleted
      : NULL;

    return [
      "id" => $this->id,
      "note" => $this->note,
      "exerciseAssignmentId" => $this->assignment->getId(),
      "solution" => $this->solution,
      "runtimeEnvironmentId" => $this->solution->getRuntimeEnvironment()->getId(),
      "maxPoints" => $this->getMaxPoints(),
      "accepted" => $this->accepted,
      "bonusPoints" => $this->bonusPoints
    ];
  }

  /**
   * @return array
   */
  public function jsonSerialize() {
    return $this->getData($this->assignment->getCanViewLimitRatios());
  }

  /**
   * The name of the user
   * @param string $note
   * @param Assignment $assignment
   * @param User $submitter The logged in user - might be the student or his/her supervisor
   * @param Solution $solution
   * @param string $jobConfigPath
   * @param AssignmentSolution $originalSubmission
   * @return AssignmentSolution
   * @throws ForbiddenRequestException
   */
  public static function createSubmission(
    string $note,
    Assignment $assignment,
    User $submitter,
    Solution $solution,
    string $jobConfigPath,
    ?AssignmentSolution $originalSubmission = NULL
  ) {
    $entity = new AssignmentSolution;
    $entity->assignment = $assignment;
    $entity->note = $note;
    $entity->solution = $solution;
    $entity->accepted = false;
    $entity->bonusPoints = 0;

    return $entity;
  }

}
