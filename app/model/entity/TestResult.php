<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Nette\Utils\Json;

/**
 * @ORM\Entity
 */
class TestResult implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

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
   * @ORM\Column(type="string")
   */
  protected $judgeOutput;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "testName" => $this->testName,
      "submissionEvaluationId" => $this->submissionEvaluation->getId(),
      "memoryExceeded" => $this->memoryExceeded,
      "timeExceeded" => $this->timeExceeded,
      "message" => $this->message
    ];
  }

  public static function createTestResult(
    SubmissionEvaluation $evaluation,
    string $name,
    string $status,
    string $judgeOutput,
    array $stats,
    array $limits
  ) {
    $result = new TestResult;
    $result->submissionEvaluation = $evaluation;
    $result->name = $name;
    $result->score = static::getScore($judgeOutput);
    $result->exitCode = $stats["exitcode"];
    $result->usedMemoryRatio = floatval($stats["memory"]) / floatval($limits["memory"]);
    $result->memoryExceeded = $result->usedMemoryRatio > 1;
    $result->usedMemoryRatio = floatval($stats["time"]) / floatval($limits["time"]);
    $result->timeExceeded = $result->usedTimeRatio > 1;
    $result->message = $stats["msg"];
    $result->judgeOutput = $judgeOutput;
    $result->stats = Json::encode($stats);
    $evaluation->getTestResults()->add($result);
    return $result;
  }

  private static function getScore(string $judgeOutput): float {
    if (empty($judgeOutput)) {
      return 0;
    }

    return min(1, max(0, floatval(strtok($judgeOutput, " "))));
  }

}
