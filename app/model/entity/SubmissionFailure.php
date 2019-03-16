<?php
namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;

/**
 * @ORM\Entity
 *
 * @method AssignmentSolutionSubmission getAssignmentSolutionSubmission()
 * @method string getDescription()
 * @method DateTime getCreatedAt()
 * @method string getResolutionNote()
 * @method string getType()
 */
class SubmissionFailure implements JsonSerializable {

  use MagicAccessors;

  /**
   * Broker rejected the submission. This happens when there is no worker who can evaluate it.
   */
  const TYPE_BROKER_REJECT = "broker_reject";

  /**
   * Evaluation failed after the job has been accepted.
   */
  const TYPE_EVALUATION_FAILURE = "evaluation_failure";

  /**
   * Evaluation finished, but its results could not be loaded
   */
  const TYPE_LOADING_FAILURE = "loading_failure";

  /**
   * The exercise configuration is invalid and it cannot be compiled
   */
  const TYPE_CONFIG_ERROR = "config_error";

  /**
   * The exercise configuration is invalid and it cannot be compiled, due to user error
   */
  const TYPE_SOFT_CONFIG_ERROR = "soft_config_error";

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $type;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\ManyToOne(targetEntity="AssignmentSolutionSubmission", inversedBy="failures")
   * @ORM\JoinColumn(nullable=true)
   */
  protected $assignmentSolutionSubmission;

  /**
   * @ORM\ManyToOne(targetEntity="ReferenceSolutionSubmission", inversedBy="failures")
   * @ORM\JoinColumn(nullable=true)
   */
  protected $referenceSolutionSubmission;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   * @var DateTime
   */
  protected $resolvedAt;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $resolutionNote;

  /**
   * SubmissionFailure constructor.
   * @param string $type
   * @param string $description
   * @param AssignmentSolutionSubmission|null $submission
   * @param ReferenceSolutionSubmission|null $referenceSolutionSubmission
   * @param DateTime|null $createdAt
   */
  private function __construct(string $type, string $description,
      AssignmentSolutionSubmission $submission = null,
      ReferenceSolutionSubmission $referenceSolutionSubmission = null,
      DateTime $createdAt = null) {
    $this->type = $type;
    $this->description = $description;
    $this->assignmentSolutionSubmission = $submission;
    $this->referenceSolutionSubmission = $referenceSolutionSubmission;
    $this->createdAt = $createdAt ?: new DateTime();
  }

  public static function forSubmission(string $type, string $description, AssignmentSolutionSubmission $submission, DateTime $createdAt = null) {
    return new static($type, $description, $submission, null, $createdAt);
  }

  public static function forReferenceSubmission(string $type, string $description, ReferenceSolutionSubmission $evaluation, DateTime $createdAt = null) {
    return new static($type, $description, null, $evaluation, $createdAt);
  }

  public function resolve(string $note, DateTime $resolvedAt = null) {
    $this->resolvedAt = $resolvedAt ?: new DateTime();
    $this->resolutionNote = $note;
  }

  public function getSubmission(): Submission {
    return $this->assignmentSolutionSubmission ?? $this->referenceSolutionSubmission;
  }

  public function isConfigErrorFailure(): bool {
    return $this->type === self::TYPE_CONFIG_ERROR || $this->type === self::TYPE_SOFT_CONFIG_ERROR;
  }

  public function toSimpleArray() {
    return [
      "description" => $this->description,
      "createdAt" => $this->createdAt->getTimestamp(),
      "resolvedAt" => $this->resolvedAt ? $this->resolvedAt->getTimestamp() : null,
      "resolutionNote" => $this->resolutionNote
    ];
  }

  public function jsonSerialize() {
    $assignmentSolution = $this->assignmentSolutionSubmission ? $this->assignmentSolutionSubmission->getAssignmentSolution() : null;
    $assignment = $assignmentSolution ? $assignmentSolution->getAssignment() : null;
    $referenceSolution = $this->referenceSolutionSubmission ? $this->referenceSolutionSubmission->getReferenceSolution() : null;
    $exercise = $referenceSolution ? $referenceSolution->getExercise() : null;

    return [
      "id" => $this->id,
      "type" => $this->type,
      "description" => $this->description,
      "createdAt" => $this->createdAt->getTimestamp(),
      "resolvedAt" => $this->resolvedAt ? $this->resolvedAt->getTimestamp() : null,
      "resolutionNote" => $this->resolutionNote,
      "assignmentSolutionId" => $assignmentSolution ? $assignmentSolution->getId() : null,
      "assignmentId" => $assignment ? $assignment->getId() : null,
      "referenceSolutionId" => $referenceSolution ? $referenceSolution->getId() : null,
      "exerciseId" => $exercise ? $exercise->getId() : null
    ];
  }
}
