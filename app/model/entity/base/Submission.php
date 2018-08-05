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
 * @method User getSubmittedBy()
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

  public function canBeEvaluated(): bool {
    return $this->resultsUrl !== null;
  }


  public function __construct(User $submittedBy, string $jobConfigPath) {
    $this->submittedAt = new DateTime();
    $this->submittedBy = $submittedBy;
    $this->jobConfigPath = $jobConfigPath;
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

  public abstract function getJobType(): string;

  public abstract function getExercise(): IExercise;

  public abstract function getAuthor(): User;
}
