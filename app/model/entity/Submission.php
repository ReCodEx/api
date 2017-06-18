<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Nette\Http\IResponse;

use App\Exceptions\ForbiddenRequestException;
use App\Helpers\EvaluationStatus as ES;


/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getNote()
 * @method Solution getSolution()
 * @method Assignment getAssignment()
 * @method string getResultsUrl()
 * @method User getUser()
 * @method setResultsUrl(string $url)
 */
class Submission implements JsonSerializable, ES\IEvaluable
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
     * @ORM\Column(type="datetime")
     */
    protected $submittedAt;

    /**
     * @ORM\Column(type="text")
     */
    protected $note;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $resultsUrl;

    public function canBeEvaluated(): bool {
      return $this->resultsUrl !== NULL;
    }

    /**
     * @var Assignment
     * @ORM\ManyToOne(targetEntity="Assignment")
     */
    protected $assignment;

  /**
   * @var Submission
   * @ORM\ManyToOne(targetEntity="Submission")
   */
    protected $originalSubmission;

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
     * @ORM\Column(type="boolean")
     */
    protected $private = FALSE;

    public function isPrivate() {
      return $this->private;
    }

    public function isPublic() {
      return !$this->private;
    }

    public function setPrivate($private = TRUE) {
      $this->private = $private;
    }

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $submittedBy;

    /**
     * @ORM\ManyToOne(targetEntity="Solution", cascade={"persist"})
     */
    protected $solution;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $asynchronous;

    public function isAsynchronous(): bool {
      return $this->asynchronous;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $accepted;

    public function isAccepted(): bool {
      return $this->accepted;
    }

    /**
     * @ORM\OneToOne(targetEntity="SolutionEvaluation", cascade={"persist", "remove"})
     */
    protected $evaluation;

    public function hasEvaluation(): bool {
      return $this->evaluation !== NULL;
    }

    public function getEvaluation(): SolutionEvaluation {
      return $this->evaluation;
    }

    public function getEvaluationStatus(): string {
      return ES\EvaluationStatus::getStatus($this);
    }

    public function setEvaluation(SolutionEvaluation $evaluation) {
      $this->evaluation = $evaluation;
      $this->solution->setEvaluated(TRUE);
    }

    public function getEvaluationSummary() {
      $summary = [
        "id" => $this->id,
        "evaluationStatus" => $this->getEvaluationStatus()
      ];

      if ($this->evaluation) {
        $summary = array_merge($summary, [
          "score" => $this->evaluation->getScore(),
          "points" => $this->evaluation->getPoints(),
          "bonusPoints" => $this->evaluation->getBonusPoints()
        ]);
      }

      return $summary;
    }

    public function getTotalPoints() {
      if ($this->evaluation === NULL) {
        return 0;
      }

      return $this->evaluation->getTotalPoints();
    }

    public function getData($canViewDetails = FALSE) {
      $evaluation = $this->hasEvaluation()
        ? $this->getEvaluation()->getData($canViewDetails)
        : NULL;

      return [
        "id" => $this->id,
        "userId" => $this->user->getId(),
        "submittedBy" => $this->submittedBy ? $this->submittedBy->getId() : NULL,
        "note" => $this->note,
        "exerciseAssignmentId" => $this->assignment->getId(),
        "submittedAt" => $this->submittedAt->getTimestamp(),
        "evaluationStatus" => ES\EvaluationStatus::getStatus($this),
        "evaluation" => $evaluation,
        "files" => $this->solution->getFiles()->getValues(),
        "maxPoints" => $this->getMaxPoints(),
        "accepted" => $this->accepted,
        "originalSubmissionId" => $this->originalSubmission !== NULL ? $this->originalSubmission->getId() : NULL
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
   * @param User $author The author of the solution
   * @param User $submitter The logged in user - might be the student or his/her supervisor
   * @param Solution $solution
   * @param bool $asynchronous Flag if submitted by student (FALSE) or supervisor (TRUE)
   * @param Submission $originalSubmission
   * @return Submission
   * @throws ForbiddenRequestException
   * @internal param array $files The submitted files
   * @internal param RuntimeConfig $runtime Runtime configuration
   */
    public static function createSubmission(
      string $note,
      Assignment $assignment,
      User $author,
      User $submitter,
      Solution $solution,
      bool $asynchronous = false,
      ?Submission $originalSubmission = NULL
    ) {
      // the author must be a student and the submitter must be either this student, or a supervisor of their group
      if ($assignment->getGroup()->hasValidLicence() === FALSE) {
        throw new ForbiddenRequestException("Your institution '{$assignment->getGroup()->getInstance()->getName()}' does not have a valid licence and you cannot submit solutions for any assignment in this group '{$assignment->getGroup()->getName()}'. Contact your supervisor for assistance.",
          IResponse::S402_PAYMENT_REQUIRED);
      }

      // now that the conditions for submission are validated, here comes the easy part:
      $entity = new Submission;
      $entity->assignment = $assignment;
      $entity->user = $author;
      $entity->note = $note;
      $entity->submittedAt = new \DateTime;
      $entity->submittedBy = $submitter;
      $entity->asynchronous = $asynchronous;
      $entity->solution = $solution;
      $entity->accepted = false;
      $entity->originalSubmission = $originalSubmission;

      return $entity;
    }

  function isValid(): bool {
    return $this->evaluation->isValid;
  }

  function isCorrect(): bool {
    return $this->evaluation->isCorrect();
  }
}
