<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use App\Helpers\EvaluationStatus\IEvaluable;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;

/**
 * @ORM\MappedSuperclass
 *
 * @method string getId()
 * @method string getResultsUrl()
 * @method string setResultsUrl(string $url)
 * @method string getJobConfigPath()
 * @method DateTime getSubmittedAt()
 */
abstract class Submission implements IEvaluable
{
  use MagicAccessors;

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
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $submittedBy;

  public function getSubmittedBy(): ?User {
    return $this->submittedBy->isDeleted() ? null : $this->submittedBy;
  }

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $resultsUrl;

  /**
   * @ORM\Column(type="string")
   */
  protected $jobConfigPath;

  /**
   * @ORM\OneToOne(targetEntity="SolutionEvaluation", cascade={"persist", "remove"})
   * @var SolutionEvaluation
   */
  protected $evaluation;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isDebug;


  public function __construct(User $submittedBy, string $jobConfigPath, bool $isDebug = false) {
    $this->submittedAt = new DateTime();
    $this->submittedBy = $submittedBy;
    $this->jobConfigPath = $jobConfigPath;
    $this->isDebug = $isDebug;
  }

  public function canBeEvaluated(): bool {
    return $this->resultsUrl !== null;
  }

  public function hasEvaluation(): bool {
    return $this->evaluation !== null;
  }

  public function getEvaluation(): ?SolutionEvaluation {
    return $this->evaluation;
  }

  public function setEvaluation(SolutionEvaluation $evaluation) {
    $this->evaluation = $evaluation;
  }

  public function isDebug(): bool {
    return $this->isDebug;
  }

  public abstract function getJobType(): string;

  public abstract function getExercise(): IExercise;

  public abstract function getAuthor(): User;
}
