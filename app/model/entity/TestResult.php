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
    ER\ITestResult $result
  ) {
    $this->submissionEvaluation = $evaluation;
    $this->testName = $result->getId();
    $this->status = $result->getStatus();
    $this->score = $result->getScore();
    $this->exitCode = $result->getExitCode();
    $this->usedMemoryRatio = $result->getUsedMemoryRatio();
    $this->memoryExceeded = !$result->isMemoryOK();
    $this->usedTimeRatio = $result->getUsedTimeRatio();
    $this->timeExceeded = !$result->isTimeOK();
    $this->message = $result->getMessage();
    $this->judgeOutput = $result->getJudgeOutput();
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
   * @ORM\ManyToOne(targetEntity="SolutionEvaluation", inversedBy="testResults")
   */
  protected $solutionEvaluation;

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
      "solutionEvaluationId" => $this->solutionEvaluation->getId(),
      "status" => $this->status,
      "score" => $this->score,
      "memoryExceeded" => $this->memoryExceeded,
      "timeExceeded" => $this->timeExceeded,
      "message" => $this->message
    ];
  }

}
