<?php

namespace App\Helpers;

use App\Model\Entity\Submission;
use App\Model\Entity\SolutionEvaluation;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Helpers\JobConfig\Storage as JobConfigStorage;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;


/**
 * Load points for given solution evaluation.
 */
class EvaluationPointsLoader {

  /**
   * @var ScoreCalculatorAccessor
   */
  private $calculators;

  /**
   * EvaluationPointsLoader constructor.
   * @param ScoreCalculatorAccessor $calculators
   */
  public function __construct(ScoreCalculatorAccessor $calculators) {
    $this->calculators = $calculators;
  }


  /**
   * @param SolutionEvaluation $evaluation
   */
  public function setStudentScoreAndPoints(SolutionEvaluation $evaluation) {
    $submission = $evaluation->getSubmission();
    if ($submission === null) {
      // not a student submission
      return;
    }

    $score = 0;
    $maxPoints = $submission->getMaxPoints();
    $calculator = $this->calculators->getCalculator($submission->getAssignment()->getScoreCalculator());

    // calculate scores for all tests
    $scores = [];
    foreach ($evaluation->getTestResults() as $testResult) {
      $scores[$testResult->getTestName()] = $testResult->getScore();
    }

    // calculate percentual score of whole submission
    if ($calculator !== NULL && !$evaluation->getInitFailed()) {
      $score = $calculator->computeScore($submission->getAssignment()->getScoreConfig(), $scores);
    }

    // calculate points from the score
    $points = floor($score * $maxPoints);

    // if the submission does not meet point threshold, it does not deserve any points
    if ($submission !== NULL) {
      $threshold = $submission->getPointsThreshold();
      if ($points < $threshold) {
        $points = 0;
      }
    }

    // ... and set results
    $evaluation->setScore($score);
    $evaluation->setPoints($points);
  }

  /**
   * Evaluation score as a mean of all score values.
   * @param SolutionEvaluation $evaluation
   */
  public function setReferenceScore(SolutionEvaluation $evaluation) {
    $testsCount = $evaluation->getTestResults()->count();
    if ($testsCount === 0) {
      return;
    }

    $score = 0;
    foreach ($evaluation->getTestResults() as $testResult) {
      $score += $testResult->getScore();
    }

    // ... and set results
    $evaluation->setScore($score / $testsCount);
  }

}
