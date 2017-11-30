<?php

namespace App\Helpers;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\SolutionEvaluation;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Helpers\JobConfig\Storage as JobConfigStorage;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Model\Entity\Submission;

/**
 * Load evaluation for given submission. This may require connecting to the file server,
 * download the results, parsing and evaluating them.
 */
class EvaluationLoader {

  /** @var FileServerProxy Authorized instance providing operations with file server */
  private $fileServer;

  /** @var JobConfigStorage */
  private $jobConfigStorage;

  /** @var EvaluationPointsLoader */
  private $pointsLoader;

  /**
   * Constructor
   * @param FileServerProxy $fsp Configured class instance providing access to remote file server
   * @param JobConfigStorage $storage
   * @param EvaluationPointsLoader $pointsLoader
   */
  public function __construct(FileServerProxy $fsp, JobConfigStorage $storage,
      EvaluationPointsLoader $pointsLoader) {
    $this->fileServer = $fsp;
    $this->jobConfigStorage = $storage;
    $this->pointsLoader = $pointsLoader;
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

    $evaluation = new SolutionEvaluation($results);
    $submission->setEvaluation($evaluation);
    if ($submission instanceof AssignmentSolutionSubmission) {
      $this->pointsLoader->setStudentScoreAndPoints($submission);
    } else if ($submission instanceof ReferenceSolutionSubmission) {
      $this->pointsLoader->setReferenceScore($submission);
    }
    return $evaluation;
  }

  /**
   * Downloads and parses the results report from the server.
   * @param Submission $submission The submission
   * @return EvaluationResults Parsed submission results
   * @throws SubmissionEvaluationFailedException
   */
  private function getResults(Submission $submission) {
    if (!$submission->getResultsUrl()) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    $jobConfigPath = $submission->getJobConfigPath();
    try {
      $jobConfig = $this->jobConfigStorage->get($jobConfigPath);
      $jobConfig->getSubmissionHeader()->setId($submission->getId())->setType($submission->getJobType());
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
}
