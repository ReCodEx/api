<?php

namespace App\Helpers;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\SolutionEvaluation;
use App\Model\Entity\ReferenceSolutionSubmission;
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
   * @param AssignmentSolution $submission The submission
   * @return SolutionEvaluation|NULL  Evaluated results for given submission
   * @throws SubmissionEvaluationFailedException
   */
  public function load(AssignmentSolution $submission) {
    $results = $this->getResults($submission);
    if (!$results) {
      return NULL;
    }

    $evaluation = new SolutionEvaluation($results, $submission);
    $submission->setEvaluation($evaluation); // TODO: setEvaluation deleted
    $this->pointsLoader->setStudentScoreAndPoints($evaluation);
    return $evaluation;
  }

  /**
   * Downloads and parses the results report from the server.
   * @param AssignmentSolution $submission The submission
   * @return EvaluationResults Parsed submission results
   * @throws SubmissionEvaluationFailedException
   */
  private function getResults(AssignmentSolution $submission) {
    if (!$submission->getResultsUrl()) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    $jobConfigPath = $submission->getJobConfigPath();
    try {
      $jobConfig = $this->jobConfigStorage->get($jobConfigPath);
      $jobConfig->getSubmissionHeader()->setId($submission->getId())->setType(AssignmentSolution::JOB_TYPE);
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
   * @param ReferenceSolutionSubmission $referenceSolution The reference solution submission
   * @return SolutionEvaluation|NULL  Evaluated results for given submission
   * @throws SubmissionEvaluationFailedException
   */
  public function loadReference(ReferenceSolutionSubmission $referenceSolution) {
    $results = $this->getReferenceResults($referenceSolution);
    if (!$results) {
      return NULL;
    }

    $evaluation = new SolutionEvaluation($results, null, $referenceSolution);
    $referenceSolution->setEvaluation($evaluation);
    $this->pointsLoader->setReferenceScore($evaluation);
    return $evaluation;
  }

  /**
   * Downloads and parses the results report from the server.
   * @param ReferenceSolutionSubmission $evaluation The reference solution submission
   * @return EvaluationResults Parsed submission results
   * @throws SubmissionEvaluationFailedException
   */
  private function getReferenceResults(ReferenceSolutionSubmission $evaluation) {
    if (!$evaluation->getResultsUrl()) {
      throw new SubmissionEvaluationFailedException("Results location is not known - evaluation cannot proceed.");
    }

    $jobConfigPath = $evaluation->getJobConfigPath();
    try {
      $jobConfig = $this->jobConfigStorage->get($jobConfigPath);
      $jobConfig->getSubmissionHeader()->setId($evaluation->getId())->setType(ReferenceSolutionSubmission::JOB_TYPE);
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
