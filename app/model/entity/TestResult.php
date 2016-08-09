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
   * @ORM\Column(type="string")
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

  public static function createTestResult(
    SubmissionEvaluation $evaluation,
    string $name,
    string $status,
    float $score,
    string $judgeOutput,
    array $stats,
    array $limits
  ) {
    $result = new TestResult;
    $result->submissionEvaluation = $evaluation;
    $result->testName = $name;
    $result->status = $status;
    $result->score = $score;
    $result->exitCode = $stats["exitcode"];
    $result->usedMemoryRatio = floatval($stats["memory"]) / floatval($limits["memory"]);
    $result->memoryExceeded = $result->usedMemoryRatio > 1;
    $result->usedTimeRatio = floatval($stats["time"]) / floatval($limits["time"]);
    $result->timeExceeded = $result->usedTimeRatio > 1;
    $result->message = $stats["message"];
    $result->judgeOutput = $judgeOutput;
    $result->stats = Json::encode($stats);
    return $result;
  }

}
