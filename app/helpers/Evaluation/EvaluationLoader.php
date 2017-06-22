<?php

namespace App\Helpers;

use App\Model\Entity\Submission;
use App\Model\Entity\SolutionEvaluation;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Helpers\JobConfig\Storage as JobConfigStorage;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;
use App\Exceptions\SubmissionEvaluationFailedException;

/**
 * Load evaluation for given submission. This may require connecting to the file server,
 * download the results, parsing and evaluating them.
 */
class EvaluationLoader {

  /** @var FileServerProxy Authorized instance providing operations with file server */
  private $fileServer;

  /** @var ScoreCalculatorAccessor */
  private $calculators;

  /** @var JobConfigStorage */
  private $jobConfigStorage;

  /**
   * Constructor
   * @param FileServerProxy $fsp Configured class instance providing access to remote file server
   * @param ScoreCalculatorAccessor $calculators
   * @param JobConfigStorage $storage
   */
  public function __construct(FileServerProxy $fsp, ScoreCalculatorAccessor $calculators, JobConfigStorage $storage) {
    $this->fileServer = $fsp;
    $this->calculators = $calculators;
    $this->jobConfigStorage = $storage;
  }

  /**
   * Downloads and processes the results for the given submission.
   * @param Submission $submission The submission
   * @return SolutionEvaluation|NULL  Evaluated results for given submission
   * @throws SubmissionEvaluationFailedException
   */
  public function load(Submission $submission) {
    $results = $this->getResults($submission);
    if (!$results) {
      return NULL;
    }
    $assignmentScoreCalculator = $submission->getAssignment()->getScoreCalculator();
    if ($assignmentScoreCalculator) {
      $calculator = $this->calculators->getCalculator($assignmentScoreCalculator);
    } else {
      $calculator = $this->calculators->getDefaultCalculator();
    }
    return new SolutionEvaluation($results, $submission, $calculator);
  }

  /**
   * Downloads and parses the results report from the server.
   * @param Submission $submission The submission
   * @return EvaluationResults Parsed submission results
   * @throws SubmissionEvaluationFailedException
   *
   * @todo: REWRITE, runtime config is not present anymore
   */
  private function getResults(Submission $submission) {
    if (!$submission->getResultsUrl()) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    $jobConfigPath = $submission->getSolution()->getRuntimeConfig()->getJobConfigFilePath();
    try {
      $jobConfig = $this->jobConfigStorage->get($jobConfigPath);
      $jobConfig->getSubmissionHeader()->setId($submission->getId())->setType(Submission::JOB_TYPE);
      $resultsYml = $this->fileServer->downloadResults($submission->getResultsUrl());
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
   * @return SolutionEvaluation|NULL  Evaluated results for given submission
   * @throws SubmissionEvaluationFailedException
   */
  public function loadReference(ReferenceSolutionEvaluation $referenceSolution) {
    $results = $this->getReferenceResults($referenceSolution);
    if (!$results) {
      return NULL;
    }

    return new SolutionEvaluation($results, NULL, NULL);
  }

  /**
   * Downloads and parses the results report from the server.
   * @param ReferenceSolutionEvaluation $evaluation The reference solution submission
   * @return EvaluationResults Parsed submission results
   * @throws SubmissionEvaluationFailedException
   *
   * @todo: REWRITE, runtime config is not present anymore
   */
  private function getReferenceResults(ReferenceSolutionEvaluation $evaluation) {
    if (!$evaluation->getResultsUrl()) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    $jobConfigPath = $evaluation->getReferenceSolution()->getSolution()->getRuntimeConfig()->getJobConfigFilePath();
    try {
      $jobConfig = $this->jobConfigStorage->get($jobConfigPath);
      $jobConfig->getSubmissionHeader()->setId($evaluation->getId())->setType(ReferenceSolutionEvaluation::JOB_TYPE);
      $resultsYml = $this->fileServer->downloadResults($evaluation->getResultsUrl());
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
