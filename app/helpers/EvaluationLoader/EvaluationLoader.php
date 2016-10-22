<?php

namespace App\Helpers;

use App\Model\Entity\Submission;
use App\Model\Entity\SolutionEvaluation;
use App\Helpers\JobConfig\Storage as JobConfigStorage;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;
use App\Helpers\ScoreCalculatorFactory;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;
use App\Exceptions\SubmissionEvaluationFailedException;

/**
 * Load evaluation for given submission. This may require connecting to the fileserver,
 * download the results, parsing and evaluating them. 
 */
class EvaluationLoader {

  /** @var FileServerProxy Authorized instance providing operations with fileserver */
  private $fileServer;

  /**
   * Constructor
   * @param FileServerProxy $fsp Configured class instance providing access to remote fileserver
   */
  public function __construct(FileServerProxy $fsp) {
    $this->fileServer = $fsp;
  }

  /**
   * Downloads and processes the results for the given submission.
   * @param Submission $submission The submission
   * @return SubmissionEvaluation  Evaluated results for given submission
   * @throws App\Exceptions\SubmissionEvaluationFailedException
   */
  public function load(Submission $submission) {
    $results = $this->getResults($submission);
    $calculator = ScoreCalculatorFactory::create($submission->getAssignment()->getScoreConfig());
    return new SolutionEvaluation($submission, $results, $calculator);
  }

  /**
   * Downloads and parses the results report from the server.
   * @param Submission    The submission
   * @return EvaluationResults Parsed submission results
   */
  private function getResults(Submission $submission) {
    if (!$submission->resultsUrl) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    $jobConfigPath = $submission->getAssignment()->getJobConfigFilePath();
    try {
      $jobConfig = JobConfigStorage::getJobConfig($jobConfigPath); 
      $jobConfig->setJobId(Submission::JOB_TYPE, $submission->getId());
      $resultsYml = $this->fileServer->downloadResults($submission->resultsUrl);
      return EvaluationResultsLoader::parseResults($resultsYml, $jobConfig);
    } catch (ResultsLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load results.");
    } catch (JobConfigLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load or parse job config.");
    }
  }

}
