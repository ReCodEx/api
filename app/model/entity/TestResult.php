<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use App\Helpers\EvaluationResults as ER;

/**
 * @ORM\Entity
 */
class TestResult implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    SubmissionEvaluation $evaluation,
    ER\TestResult $result
  ) {
    $statsInterpret = $result->getStatsInterpretation();
    $this->submissionEvaluation = $evaluation;
    $this->testName = $result->getId();
    $this->status = $result->getStatus();
    $this->score = $result->getScore();
    $this->exitCode = $result->getStats()->getExitCode();
    $this->usedMemoryRatio = $statsInterpret->getUsedMemoryRatio();
    $this->memoryExceeded = !$statsInterpret->isMemoryOK();
    $this->usedTimeRatio = $statsInterpret->getUsedTimeRatio();
    $this->timeExceeded = !$statsInterpret->isTimeOK();
    $this->message = $result->getStats()->getMessage();
    $this->judgeOutput = $result->getStats()->getJudgeOutput();
    $this->stats = (string) $result->getStats();
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="integer")
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $testName;

  /**
   * @ORM\Column(type="string")
   */
  protected $status;

  /**
   * @ORM\ManyToOne(targetEntity="SubmissionEvaluation", inversedBy="testResults")
   */
  protected $submissionEvaluation;

  /**
   * @ORM\Column(type="float")
   */
  protected $score;

  /**
    * @ORM\Column(type="boolean")
    */
  protected $memoryExceeded;
  
  /**
   * @ORM\Column(type="float")
   */
  protected $usedMemoryRatio;

  /**
    * @ORM\Column(type="boolean")
    */
  protected $timeExceeded;
  
  /**
   * @ORM\Column(type="float")
   */
  protected $usedTimeRatio;

  /**
    * @ORM\Column(type="boolean")
    */
  protected $exitCode;
  
  /**
    * @ORM\Column(type="string")
    */
  protected $message;

  /**
   * @ORM\Column(type="string")
   */
  protected $stats;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $judgeOutput;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "testName" => $this->testName,
      "submissionEvaluationId" => $this->submissionEvaluation->getId(),
      "status" => $this->status,
      "score" => $this->score,
      "memoryExceeded" => $this->memoryExceeded,
      "timeExceeded" => $this->timeExceeded,
      "message" => $this->message
    ];
  }

}
