<?php

namespace App\Helpers;

use App\Exceptions\SubmissionFailedException;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Storage;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Model\Entity\Submission;

/**
 * Class which should create submission, generate job configuration,
 * store it and at the end submit solution to backend.
 */
class SubmissionHelper {

  /** @var BackendSubmitHelper */
  private $backendSubmitHelper;

  /**
   * SubmissionHelper constructor.
   * @param BackendSubmitHelper $backendSubmitHelper
   */
  public function __construct(BackendSubmitHelper $backendSubmitHelper) {
    $this->backendSubmitHelper = $backendSubmitHelper;
  }

  /**
   * @param string $jobId
   * @param string $jobType
   * @param string $environment
   * @param array $files
   * @param JobConfig $jobConfig
   * @param null|string $hwgroup
   * @return string fileserver results URL
   * @throws SubmissionFailedException
   */
  private function internalSubmit(string $jobId, string $jobType,
      string $environment, array $files, JobConfig $jobConfig,
      ?string $hwgroup = null): string {
    // Fill in the job configuration header
    $jobConfig->getSubmissionHeader()->setId($jobId)->setType($jobType);

    // Send the submission to the broker
    $resultsUrl = NULL;

    $resultsUrl = $this->backendSubmitHelper->initiateEvaluation(
      $jobConfig,
      $files,
      ['env' => $environment],
      $hwgroup
    );

    if ($resultsUrl === NULL) {
      throw new SubmissionFailedException("The broker rejected our request");
    }

    return $resultsUrl;
  }

  /**
   *
   * @param string $jobId
   * @param string $environment
   * @param array $files
   * @param JobConfig $jobConfig
   * @return string fileserver results URL
   * @throws SubmissionFailedException
   */
  public function submit(string $jobId, string $environment, array $files,
      JobConfig $jobConfig): string {
    return $this->internalSubmit($jobId, Submission::JOB_TYPE, $environment, $files, $jobConfig);
  }

  /**
   *
   * @param string $jobId
   * @param string $environment
   * @param null|string $hwgroup
   * @param array $files
   * @param JobConfig $jobConfig
   * @return string fileserver results URL
   */
  public function submitReference(string $jobId, string $environment,
      ?string $hwgroup, array $files, JobConfig $jobConfig): string {
    return $this->internalSubmit($jobId, ReferenceSolutionEvaluation::JOB_TYPE, $environment, $files, $jobConfig, $hwgroup);
  }

}
