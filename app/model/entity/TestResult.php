<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use App\Helpers\EvaluationResults as ER;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getTestName()
 * @method float getScore()
 * @method float getUsedMemoryRatio()
 * @method int getUsedMemory()
 * @method bool getIsMemoryOK()
 * @method float getUsedWallTimeRatio()
 * @method float getUsedWallTime()
 * @method bool getIsWallTimeOK()
 * @method float getUsedCpuTimeRatio()
 * @method float getUsedCpuTime()
 * @method bool getIsCpuTimeOK()
 * @method string getJudgeOutput()
 * @method string getStatus()
 * @method string getMessage()
 * @method int getExitCode()
 * @method bool getCpuTimeExceeded()
 * @method bool getWallTimeExceeded()
 * @method bool getMemoryExceeded()
 * @method SolutionEvaluation getSolutionEvaluation()
 */
class TestResult
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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
    $this->usedWallTimeRatio = $result->getUsedWallTimeRatio();
    $this->usedWallTime = $result->getUsedWallTime();
    $this->wallTimeExceeded = !$result->isWallTimeOK();
    $this->usedCpuTimeRatio = $result->getUsedCpuTimeRatio();
    $this->usedCpuTime = $result->getUsedCpuTime();
    $this->cpuTimeExceeded = !$result->isCpuTimeOK();
    $this->message = substr($result->getMessage(), 0, 255);  // maximal size of varchar
    $this->judgeOutput = substr($result->getJudgeOutput(), 0, 65536); // the size corresponds to the length of the column
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
  protected $wallTimeExceeded;

  /**
   * @ORM\Column(type="float")
   */
  protected $usedWallTimeRatio;

  /**
   * @ORM\Column(type="float")
   */
  protected $usedWallTime;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $cpuTimeExceeded;

  /**
   * @ORM\Column(type="float")
   */
  protected $usedCpuTimeRatio;

  /**
   * @ORM\Column(type="float")
   */
  protected $usedCpuTime;

  /**
    * @ORM\Column(type="integer")
    */
  protected $exitCode;

  /**
    * @ORM\Column(type="string")
    */
  protected $message;

  /**
   * @ORM\Column(type="text", length=65536, nullable=true)
   */
  protected $judgeOutput;

  public function getData(bool $canViewRatios, bool $canViewValues, bool $canViewJudgeOutput) {
    $wallTime = null;
    $wallTimeRatio = null;
    $cpuTime = null;
    $cpuTimeRatio = null;
    $memory = null;
    $memoryRatio = null;
    $judgeLog = null;

    if ($canViewRatios) {
      $wallTimeRatio = $this->usedWallTimeRatio;
      $cpuTimeRatio = $this->usedCpuTimeRatio;
      $memoryRatio = $this->usedMemoryRatio;
    }
    if ($canViewValues) {
      $wallTime = $this->usedWallTime;
      $cpuTime = $this->usedCpuTime;
      $memory = $this->usedMemory;
    }
    if ($canViewJudgeOutput) {
      list($score, $judgeLog) = ER\EvaluationTaskResult::parseJudgeOutput($this->judgeOutput);
    }

    return [
      "id" => $this->id,
      "testName" => $this->testName,
      "solutionEvaluationId" => $this->solutionEvaluation->getId(),
      "status" => $this->status,
      "score" => $this->score,
      "memoryExceeded" => $this->memoryExceeded,
      "wallTimeExceeded" => $this->wallTimeExceeded,
      "cpuTimeExceeded" => $this->cpuTimeExceeded,
      "exitCode" => $this->exitCode,
      "message" => $this->message,
      "wallTimeRatio" => $wallTimeRatio,
      "cpuTimeRatio" => $cpuTimeRatio,
      "memoryRatio" => $memoryRatio,
      "wallTime" => $wallTime,
      "cpuTime" => $cpuTime,
      "memory" => $memory,
      "judgeLog" => $judgeLog
    ];
  }

}
