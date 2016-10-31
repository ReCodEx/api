<?php

namespace App\Model\Entity;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\NotFoundException;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\IScoreCalculator;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 */
class SolutionEvaluation implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $evaluatedAt;

  /**
   * If true, the solution cannot be compiled.
   * @ORM\Column(type="boolean")
   */
  protected $initFailed;

  /**
   * @ORM\Column(type="float")
   */
  protected $score;
  public function isCorrect() { return $this->score > 0; }

  /**
   * @ORM\Column(type="integer")
   */
  protected $points;

  /**
   * @ORM\Column(type="integer", nullable=true)
   */
  protected $bonusPoints;

  public function getTotalPoints() {
    return $this->getPoints() + $this->getBonusPoints();
  }

  /**
   * Manualy set error in evaluation.
   * @ORM\Column(type="boolean")
   */
  protected $isValid;
  public function isValid() { return $this->isValid; }

  /**
   * Automaticaly detected error in evaluation (reported by broker).
   * @ORM\Column(type="boolean")
   */
  protected $evaluationFailed;

  /**
   * @ORM\Column(type="text")
   */
  protected $resultYml;

  /**
   * @ORM\OneToMany(targetEntity="TestResult", mappedBy="solutionEvaluation", cascade={"persist"})
   */
  protected $testResults;

  /** @var array */
  private $scores;

  public function setTestResults(array $testResults) {
    $this->scores = [];
    foreach ($testResults as $result) {
      $testResult = new TestResult($this, $result);
      $this->testResults->add($testResult);
      $this->scores[$testResult->getTestName()] = $testResult->getScore();
    }
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "evaluatedAt" => $this->evaluatedAt->getTimestamp(),
      "score" => $this->score,
      "points" => $this->points,
      "bonusPoints" => $this->bonusPoints,
      "initFailed" => $this->initFailed,
      "isValid" => $this->isValid,
      "isCorrect" => $this->isCorrect(),
      "evaluationFailed" => $this->evaluationFailed,
      "testResults" => $this->testResults->getValues()
    ];
  }

  /**
   * Loads and processes the results of the submission.
   * @param  Submission $submission           The submission
   * @param  EvaluationResults   $results     The interpreted results
   * @param  IScoreCalculator    $calculator  Calculates the score from given test results
   */
  public function __construct(Submission $submission, EvaluationResults $results, IScoreCalculator $calculator) {
    $this->evaluatedAt = new \DateTime;
    $this->isValid = TRUE;
    $this->evaluationFailed = !$results;
    $this->initFailed = !!$results && $results->initOK();
    $this->resultYml = (string) $results;
    $submission->setEvaluation($this);

    // calculate the score and points
    $this->testResults = new ArrayCollection;
    $hardwareGroup = $submission->getSolution()->getHardwareGroupId();
    $this->setTestResults($results->getTestsResults($hardwareGroup));
    $this->score = $calculator->computeScore($this->scores);
    $this->bonusPoints = 0;
    $this->points = $this->score * $submission->getMaxPoints();
  }

}
