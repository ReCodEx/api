<?php

namespace App\Helpers;

use App\Model\Entity\Submission;
use App\Model\Entity\SolutionEvaluation;
use App\Helpers\JobConfig\Storage as JobConfigStorage;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;

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

  /** @var ScoreCalculatorAccessor */
  private $calculators;

  /**
   * Constructor
   * @param FileServerProxy $fsp Configured class instance providing access to remote fileserver
   * @param ScoreCalculatorAccessor $calculators
   */
  public function __construct(FileServerProxy $fsp, ScoreCalculatorAccessor $calculators) {
    $this->fileServer = $fsp;
    $this->calculators = $calculators;
  }

  /**
   * Downloads and processes the results for the given submission.
   * @param Submission $submission The submission
   * @return SolutionEvaluation  Evaluated results for given submission
   * @throws App\Exceptions\SubmissionEvaluationFailedException
   */
  public function load(Submission $submission) {
    $results = $this->getResults($submission);
    if (!$results) {
      return NULL;
    }

    $calculator = $this->calculators->getCalculator($submission->assignment->scoreCalculator);
    return new SolutionEvaluation($results, $submission, $calculator);
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

    $jobConfigPath = $submission->getSolution()->getSolutionRuntimeConfig()->getJobConfigFilePath();
    try {
      $jobConfig = JobConfigStorage::getJobConfig($jobConfigPath);
      $jobConfig->setJobId(Submission::JOB_TYPE, $submission->getId());
      $resultsYml = $this->fileServer->downloadResults($submission->resultsUrl);
      return $resultsYml === NULL
        ? NULL
        : EvaluationResultsLoader::parseResults($resultsYml, $jobConfig);
    } catch (ResultsLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load results.");
    } catch (JobConfigLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load or parse job config.");
    }
  }

  /**
   * Downloads and processes the results for the given submission.
   * @param ReferenceSolutionEvaluation $referenceSolution The reference solution submission
   * @return SolutionEvaluation  Evaluated results for given submission
   * @throws App\Exceptions\SubmissionEvaluationFailedException
   */
  public function loadReference(ReferenceSolutionEvaluation $referenceSolution) {
    $results = $this->getReferenceResults($referenceSolution);
    if (!$results) {
      return NULL;
    }

    return new SolutionEvaluation($results, NULL, NULL, $referenceSolution->getHwGroup());
  }

  /**
   * Downloads and parses the results report from the server.
   * @param ReferenceSolutionEvaluation $referenceSolution  The reference solution submission
   * @return EvaluationResults Parsed submission results
   */
  private function getReferenceResults(ReferenceSolutionEvaluation $referenceSolution) {
    if (!$referenceSolution->resultsUrl) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    $jobConfigPath = $referenceSolution->getReferenceSolution()->getSolution()->getSolutionRuntimeConfig()->getJobConfigFilePath();
    try {
      $jobConfig = JobConfigStorage::getJobConfig($jobConfigPath);
      $jobConfig->setJobId(ReferenceSolutionEvaluation::JOB_TYPE, $referenceSolution->getId());
      $resultsYml = $this->fileServer->downloadResults($referenceSolution->resultsUrl);
      return $resultsYml === NULL
        ? NULL
        : EvaluationResultsLoader::parseResults($resultsYml, $jobConfig);
    } catch (ResultsLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load results.");
    } catch (JobConfigLoadingException $e) {
      throw new SubmissionEvaluationFailedException("Cannot load or parse job config.");
    }
  }

}
