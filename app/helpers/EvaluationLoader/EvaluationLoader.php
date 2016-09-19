<?php

namespace App\Helpers;

use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionEvaluation;
use App\Helpers\JobConfig\Loader as JobConfigLoader;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;
use App\Helpers\SimpleScoreCalculator;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;
use App\Exceptions\SubmissionEvaluationFailedException;

class EvaluationLoader {

  /** @var FileServerProxy */
  private $fileServer;

  public function __construct(FileServerProxy $fsp) {
    $this->fileServer = $fsp;
  }

  /**
   * Downloads and processes the results for the given submission.
   * @param Submission $submission The submission
   * @return SubmissionEvaluation
   * @throws App\Exceptions\SubmissionEvaluationFailedException
   */
  public function load(Submission $submission) {
    $results = $this->getResults($submission);
    $evaluation = new SubmissionEvaluation($submission, $results);
    $this->calculateScore($submission, $evaluation, $results);
    $this->calculatePoints($evaluation, $submission->getMaxPoints());
    return $evaluation;
  }

  /**
   * Downloads and parses the results report from the server.
   * @param Submission    The submission
   * @return EvaluationResults 
   */
  private function getResults(Submission $submission) {
    if (!$submission->resultsUrl) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    try {
      $jobConfig = JobConfigLoader::getJobConfig($submission);
      $resultsYml = $this->fileServer->downloadResults($submission->resultsUrl);
      return EvaluationResultsLoader::parseResults($resultsYml, $jobConfig);
    } catch (ResultsLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load results.");
    } catch (JobConfigLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load or parse job config.");
    }
  }

  /**
   * @param Submission            $submission   The submission
   * @param SubmissionEvaluation  $evaluation   Evaluation entity
   * @param EvaluationResults     $results      Results of the evaluation
   * @return void
   */
  private function calculateScore(Submission $submission, SubmissionEvaluation $evaluation, EvaluationResults $results) {
    // calcutate the total score based on the results
    if (!!$results) {
      $evaluation->setTestResults($results->getTestsResults($submission->getHardwareGroup()));
      $calculator = new SimpleScoreCalculator($submission->getExerciseAssignment()->getScoreConfig());
      $evaluation->updateScore($calculator);
    } else {
      $evaluation->setScore(0);
    }
  }

  /**
   * @param SubmissionEvaluation  $evaluation   Evaluation of the submission
   * @param int                   $maxPoints    Maximum points
   * @return void
   */
  private function calculatePoints(SubmissionEvaluation $evaluation, int $maxPoints) {
    // from the score, calculate the points
    // 'getMaxPoints' is time-dependent, but we don't have to bother with that here
    $points = $evaluation->getScore() * $maxPoints;
    $evaluation->setPoints($points);
  }

}
