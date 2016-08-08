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
   * @ORM\GeneratedValue(strategy="auto")
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
   * @ORM\Column(type="integer")
   */
  protected $score;

  /**
    * @ORM\Column(type="boolean")
    */
  protected $memoryExceeded;
  
  /**
    * @ORM\Column(type="boolean")
    */
  protected $timeExceeded;

  /**
    * @ORM\Column(type="boolean")
    */
  protected $exitCode;
  
  /**
    * @ORM\Column(type="boolean")
    */
  protected $hasPassed;
  
  /**
    * @ORM\Column(type="string")
    */
  protected $message;

  /**
   * @ORM\Column(type="string")
   */
  protected $stats;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "testName" => $this->testName,
      "submissionEvaluationId" => $this->submissionEvaluation->getId(),
      "memoryExceeded" => $this->memoryExceeded,
      "timeExceeded" => $this->timeExceeded,
      "hasPassed" => $this->hasPassed,
      "message" => $this->message
    ];
  }

  public static function createTestResult(SubmissionEvaluation $evaluation, string $name, int $score, string $status, array $stats) {
    $result = new TestResult;
    $result->submissionEvaluation = $evaluation;
    $result->name = $name;
    $result->score = $score;
    $result->exitCode = $stats["exitcode"];
    $result->memoryExceeded = FALSE; // @todo
    $result->timeExceeded = FALSE; // @todo
    $result->hasPassed = $status === "OK";
    $result->message = $stats["msg"];
    $result->stats = Json::encode($stats);
    $evaluation->getTestResults()->add($result);
    return $result;
  }

}
