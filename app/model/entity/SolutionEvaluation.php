<?php

namespace App\Model\Entity;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\NotFoundException;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\IScoreCalculator;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 *
 * @method DateTime getEvaluatedAt()
 * @method bool getEvaluationFailed()
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
    return $this->points + $this->bonusPoints;
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
   * @ORM\Column(type="text")
   */
  protected $initiationOutputs;

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

  public function getData(bool $canViewRatios) {
    $testResults = $this->testResults->map(
      function ($res) use ($canViewRatios) { return $res->getData($canViewRatios); }
    )->getValues();

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
      "initiationOutputs" => $this->initiationOutputs,
      "testResults" => $testResults
    ];
  }

  public function jsonSerialize() {
    return $this->getData(FALSE);
  }

  /**
   * Loads and processes the results of the submission.
   * TODO for the sake of design purity, the parts that use the Submission should be moved to another class (even better, let's not calculate score in an entity class)
   * @param  EvaluationResults $results The interpreted results
   * @param  Submission $submission|NULL The submission. It can be null in case we're handling a reference solution evaluation
   * @param  IScoreCalculator $calculator Calculates the score from given test results
   * @throws SubmissionEvaluationFailedException
   */
  public function __construct(EvaluationResults $results, Submission $submission = NULL, IScoreCalculator $calculator = NULL) {
    $this->evaluatedAt = new \DateTime;
    $this->isValid = TRUE;
    $this->evaluationFailed = !$results;
    $this->initFailed = !$results->initOK();
    $this->resultYml = (string) $results;
    $this->score = 0;
    $maxPoints = 0;
    $this->testResults = new ArrayCollection;
    $this->initiationOutputs = $results->getInitiationOutputs();

    if ($submission !== NULL) {
      $submission->setEvaluation($this);
      $maxPoints = $submission->getMaxPoints();
    }

    // calculate percentual score of submission
    $this->setTestResults($results->getTestsResults());
    if ($submission !== NULL && $calculator !== NULL && !$this->initFailed) {
      $this->score = $calculator->computeScore($submission->getAssignment()->getScoreConfig(), $this->scores);
    }

    // calculate points from the score
    $this->bonusPoints = 0;
    $this->points = floor($this->score * $maxPoints);

    // if the submission does not meet point threshold, it does not deserve any points
    if ($submission !== NULL) {
      $threshold = $submission->getPointsThreshold();
      if ($this->points < $threshold) {
        $this->points = 0;
      }
    }
  }

}
