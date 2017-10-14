<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use App\Helpers\EvaluationResults as ER;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getTestName()
 * @method float getScore()
 */
class TestResult implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    SolutionEvaluation $evaluation,
    ER\TestResult $result
  ) {
    $this->solutionEvaluation = $evaluation;
    $this->testName = $result->getId();
    $this->status = $result->getStatus();
    $this->score = $result->getScore();
    $this->exitCode = $result->getExitCode();
    $this->usedMemoryRatio = $result->getUsedMemoryRatio();
    $this->usedMemory = $result->getUsedMemory();
    $this->memoryExceeded = !$result->isMemoryOK();
    $this->usedTimeRatio = $result->getUsedTimeRatio();
    $this->usedTime = $result->getUsedTime();
    $this->timeExceeded = !$result->isTimeOK();
    $this->message = $result->getMessage();
    $this->judgeOutput = $result->getJudgeOutput();
    $this->stats = implode(",", array_map(function ($stat) { return (string) $stat; }, $result->getStats()));

    $this->tasks = new ArrayCollection;
    foreach ($result->getExecutionResults() as $executionResult) {
      $stats = $executionResult->getStats();
      $newTask = new TaskResult($executionResult->getId(), $stats->getUsedTime(),
          $stats->getUsedMemory(), $executionResult->getOutput(), $this);
      $this->tasks->add($newTask);
    }
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
   * @ORM\Column(type="integer")
   */
  protected $usedMemory;

  /**
    * @ORM\Column(type="boolean")
    */
  protected $timeExceeded;

  /**
   * @ORM\Column(type="float")
   */
  protected $usedTimeRatio;

  /**
   * @ORM\Column(type="float")
   */
  protected $usedTime;

  /**
    * @ORM\Column(type="integer")
    */
  protected $exitCode;

  /**
    * @ORM\Column(type="string")
    */
  protected $message;

  /**
   * @ORM\Column(type="text")
   */
  protected $stats;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $judgeOutput;

  /**
   * @ORM\OneToMany(targetEntity="TaskResult", mappedBy="testResult", cascade={"persist"})
   */
  protected $tasks;

  public function getData(bool $canViewRatios, bool $canViewValues = false) {
    $time = NULL;
    $timeRatio = NULL;
    $memory = NULL;
    $memoryRatio = NULL;

    if ($canViewRatios) {
      $timeRatio = $this->usedTimeRatio;
      $memoryRatio = $this->usedMemoryRatio;
    }
    if ($canViewValues) {
      $time = $this->usedTime;
      $memory = $this->usedMemory;
    }

    return [
      "id" => $this->id,
      "testName" => $this->testName,
      "solutionEvaluationId" => $this->solutionEvaluation->getId(),
      "status" => $this->status,
      "score" => $this->score,
      "memoryExceeded" => $this->memoryExceeded,
      "timeExceeded" => $this->timeExceeded,
      "exitCode" => $this->exitCode,
      "message" => $this->message,
      "timeRatio" => $timeRatio,
      "memoryRatio" => $memoryRatio,
      "time" => $time,
      "memory" => $memory
    ];
  }

  public function jsonSerialize() {
    return $this->getData(FALSE);
  }

}
