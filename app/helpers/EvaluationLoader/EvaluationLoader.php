<?php

namespace App\Helpers;

use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionEvaluation;
use App\Helpers\JobConfig\Loader as JobConfigLoader;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;
use App\Helpers\ScoreCalculatorFactory;

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
    $calculator = ScoreCalculatorFactory::create($submission->getExerciseAssignment()->getScoreConfig());
    return new SubmissionEvaluation($submission, $results, $calculator);
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

    $jobConfigPath = $submission->getExerciseAssignment()->getJobConfigFilePath();
    try {
      $jobConfig = JobConfigLoader::getJobConfig($jobConfigPath); 
      $jobConfig->setJobId($submission->getId());
      $resultsYml = $this->fileServer->downloadResults($submission->resultsUrl);
      return EvaluationResultsLoader::parseResults($resultsYml, $jobConfig);
    } catch (ResultsLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load results.");
    } catch (JobConfigLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load or parse job config.");
    }
  }

}
