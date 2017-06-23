<?php

namespace App\Helpers;

use App\Exceptions\SubmissionFailedException;
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

  /** @var Storage */
  private $storage;

    /**
     * SubmissionHelper constructor.
     * @param BackendSubmitHelper $backendSubmitHelper
     * @param Storage $storage
     */
  public function __construct(BackendSubmitHelper $backendSubmitHelper, Storage $storage) {
    $this->backendSubmitHelper = $backendSubmitHelper;
    $this->storage = $storage;
  }

    /**
     * @param string $jobId
     * @param string $jobType
     * @param string $environment
     * @param array $files
     * @param string $jobConfigPath
     * @param null|string $hwgroup
     * @return array first element is JobConfig and second fileserver results URL
     * @throws SubmissionFailedException
     */
  private function internalSubmit(string $jobId, string $jobType,
      string $environment, array $files, string $jobConfigPath,
      ?string $hwgroup = null): array {
    // Fill in the job configuration header
    $jobConfig = $this->storage->get($jobConfigPath);
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

    return [ $jobConfig, $resultsUrl ];
  }

  /**
   *
   * @param string $jobId
   * @param string $environment
   * @param array $files
   * @param string $jobConfigPath
   * @return array first element is JobConfig and second fileserver results URL
   * @throws SubmissionFailedException
   */
  public function submit(string $jobId, string $environment, array $files,
      string $jobConfigPath): array {
    return $this->internalSubmit($jobId, Submission::JOB_TYPE, $environment, $files, $jobConfigPath);
  }

    /**
     *
     * @param string $jobId
     * @param string $environment
     * @param null|string $hwgroup
     * @param array $files
     * @param string $jobConfigPath
     * @return string fileserver results URL
     */
  public function submitReference(string $jobId, string $environment,
      ?string $hwgroup, array $files, string $jobConfigPath): string {
    return $this->internalSubmit($jobId, ReferenceSolutionEvaluation::JOB_TYPE, $environment, $files, $jobConfigPath, $hwgroup)[1];
  }

}
