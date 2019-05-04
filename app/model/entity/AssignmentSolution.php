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
 * @method bool getAccepted()
 * @method setAccepted(bool $accepted)
 * @method int getBonusPoints()
 * @method setBonusPoints(int $points)
 * @method int getOverriddenPoints()
 * @method setOverriddenPoints(?int $points)
 * @method Collection getSubmissions()
 * @method ?AssignmentSolutionSubmission getLastSubmission()
 * @method setLastSubmission(AssignmentSolutionSubmission)
 */
class AssignmentSolution
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

  public function getAssignment(): ?Assignment {
    return $this->assignment->isDeleted() ? null : $this->assignment;
  }

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
   * @ORM\ManyToOne(targetEntity="Solution", cascade={"persist", "remove"}, fetch="EAGER")
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
   * @ORM\Column(type="integer", nullable=true)
   */
  protected $overriddenPoints;

  /**
   * Get points acquired by evaluation. If evaluation is not present return null.
   * @return int|null
   */
  public function getPoints(): ?int {
    if ($this->overriddenPoints !== null) {
      return $this->overriddenPoints;
    }

    if ($this->lastSubmission === null) {
      return null;
    }

    $evaluation = $this->lastSubmission->getEvaluation();
    if ($evaluation === null) {
      return null;
    }

    return $evaluation->getPoints();
  }

  /**
   * Get total points which this solution got. Including bonus points and points
   * for evaluation.
   * @return int
   */
  public function getTotalPoints() {
    $points = $this->getPoints() ?? 0;
    return $points + $this->bonusPoints;
  }

  /**
   * Note that order by annotation has to be present!
   *
   * @ORM\OneToMany(targetEntity="AssignmentSolutionSubmission", mappedBy="assignmentSolution", cascade={"remove"})
   * @ORM\OrderBy({"submittedAt" = "DESC"})
   */
  protected $submissions;

  /**
   * This is a reference to the last (by submittedAt) submission attached to this solution.
   * The reference should speed up loading in many cases since the last submission is the only one that counts.
   * However, in the future, this behavior might be altered, so we can activeley select, which submission is "relevant".
   * 
   * @ORM\OneToOne(targetEntity="AssignmentSolutionSubmission", fetch="EAGER")
   * @var AssignmentSolutionSubmission|null
   */
  protected $lastSubmission = null;

  /**
   * @return string[]
   */
  public function getSubmissionsIds(): array {
    return $this->submissions->map(function (AssignmentSolutionSubmission $submission) {
      return $submission->getId();
    })->getValues();
  }

  /**
   * AssignmentSolution constructor.
   */
  private function __construct() {
    $this->accepted = false;
    $this->bonusPoints = 0;
    $this->submissions = new ArrayCollection();
    $this->overriddenPoints = null;
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
    $entity = new AssignmentSolution();
    $entity->assignment = $assignment;
    $entity->note = $note;
    $entity->solution = $solution;

    return $entity;
  }

}
