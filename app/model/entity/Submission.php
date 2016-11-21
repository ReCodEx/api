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
 * @method Solution getSolution()
 * @method User getUser()
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

    public function getMaxPoints() {
      return $this->assignment->getMaxPoints($this->submittedAt);
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
     * @ORM\OneToOne(targetEntity="Solution", cascade={"persist"})
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

    /**
     * @return array
     */
    public function jsonSerialize() {
      $evaluation = $this->hasEvaluation() ? $this->getEvaluation()->getData($this->assignment->getCanViewLimitRatios()) : NULL;
      return [
        "id" => $this->id,
        "userId" => $this->getUser()->getId(),
        "submittedBy" => $this->getSubmittedBy() ? $this->getSubmittedBy()->getId() : NULL,
        "note" => $this->note,
        "exerciseAssignmentId" => $this->assignment->getId(),
        "submittedAt" => $this->submittedAt->getTimestamp(),
        "evaluationStatus" => ES\EvaluationStatus::getStatus($this),
        "evaluation" => $evaluation,
        "files" => $this->getSolution()->getFiles()->getValues(),
        "maxPoints" => $this->getMaxPoints()
      ];
    }

  /**
   * The name of the user
   * @param string $note
   * @param Assignment $assignment
   * @param User $user The user who submits the solution
   * @param User $loggedInUser The logged in user - might be the student or his/her supervisor
   * @param Solution $solution
   * @param bool $asynchronous Flag if submitted by student (FALSE) or supervisor (TRUE)
   * @return Submission
   * @throws ForbiddenRequestException
   * @internal param array $files The submitted files
   * @internal param SolutionRuntimeConfig $runtime Runtime configuration
   */
    public static function createSubmission(
      string $note,
      Assignment $assignment,
      User $user,
      User $loggedInUser,
      Solution $solution,
      bool $asynchronous = false
    ) {
      // the "user" must be a student and the "loggedInUser" must be either this student, or a supervisor of this group
      if ($assignment->canAccessAsStudent($user) === FALSE &&
        ($user->getId() === $loggedInUser->getId()
          && $assignment->canAccessAsSupervisor($loggedInUser) === FALSE)) {
        throw new ForbiddenRequestException("{$user->getName()} cannot submit solutions for this assignment.");
      }

      if ($assignment->getGroup()->hasValidLicence() === FALSE) {
        throw new ForbiddenRequestException("Your institution '{$assignment->getGroup()->getInstance()->getName()}' does not have a valid licence and you cannot submit solutions for any assignment in this group '{$assignment->getGroup()->getName()}'. Contact your supervisor for assistance.",
          IResponse::S402_PAYMENT_REQUIRED);
      }

      if ($assignment->canAccessAsSupervisor($loggedInUser) === FALSE) {
        if ($assignment->isAfterDeadline() === TRUE) { // supervisors can force-submit even after the deadline
          throw new ForbiddenRequestException("It is after the deadline, you cannot submit solutions any more. Contact your supervisor for assistance.");
        }

        if ($assignment->hasReachedSubmissionsCountLimit($user)) {
          throw new ForbiddenRequestException("The limit of {$assignment->getSubmissionsCountLimit()} submissions for this assignment has been reached. You cannot submit any more solutions.");
        }
      }

      // now that the conditions for submission are validated, here comes the easy part:
      $entity = new Submission;
      $entity->assignment = $assignment;
      $entity->user = $user;
      $entity->note = $note;
      $entity->submittedAt = new \DateTime;
      $entity->submittedBy = $loggedInUser;
      $entity->asynchronous = $asynchronous;
      $entity->solution = $solution;

      return $entity;
    }

}
