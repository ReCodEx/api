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
   * Set score and points to given evaluation of student submission.
   * @param SolutionEvaluation|null $evaluation
   */
  public function setStudentScoreAndPoints(?SolutionEvaluation $evaluation) {
    $this->setStudentScore($evaluation);
    $this->setStudentPoints($evaluation);
  }

  /**
   * Set score to evaluation of student submission.
   * @param SolutionEvaluation|null $evaluation
   */
  private function setStudentScore(?SolutionEvaluation $evaluation) {
    if ($evaluation === null || $evaluation->getSubmission() === null) {
      // not a student submission
      return;
    }

    // setup
    $score = 0;

    // calculate scores for all tests
    $scores = [];
    foreach ($evaluation->getTestResults() as $testResult) {
      $scores[$testResult->getTestName()] = $testResult->getScore();
    }

    // calculate percentual score of whole submission
    $submission = $evaluation->getSubmission();
    $calculator = $this->calculators->getCalculator($submission->getAssignment()->getScoreCalculator());
    if ($calculator !== NULL && !$evaluation->getInitFailed()) {
      $score = $calculator->computeScore($submission->getAssignment()->getScoreConfig(), $scores);
    }

    // ... and set results
    $evaluation->setScore($score);
  }

  /**
   * Set points to evaluation of student submission.
   * @note Score has to be calculated before call of this function.
   * @param SolutionEvaluation|null $evaluation
   */
  public function setStudentPoints(?SolutionEvaluation $evaluation) {
    if ($evaluation === null || $evaluation->getSubmission() === null) {
      // not a student submission
      return;
    }

    // setup
    $submission = $evaluation->getSubmission();
    $maxPoints = $submission->getMaxPoints();

    // calculate points from the score
    $points = floor($evaluation->getScore() * $maxPoints);

    // if the submission does not meet point threshold, it does not deserve any points
    if ($submission !== NULL) {
      $threshold = $submission->getPointsThreshold();
      if ($points < $threshold) {
        $points = 0;
      }
    }

    // ... and set results
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
