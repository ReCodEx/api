<?php

namespace App\Helpers;

use App\Exceptions\SubmissionFailedException;
use App\Helpers\JobConfig\JobConfig;

/**
 * Easy submit new job to the backend. This means prepare the archive, upload it to the
 * fileserver and then tell broker to evaluate the submission.
 */
class SubmissionHelper {

  /** @var BrokerProxy Communication with broker */
  private $broker;

  /** @var FileServerProxy Communication with fileserver */
  private $fileServer;

  /**
   * Constructor
   * @param BrokerProxy     $bp  Initialized communication wrapper with broker
   * @param FileServerProxy $fsp Initialized communication wrapper with fileserver
   */
  public function __construct(BrokerProxy $bp, FileServerProxy $fsp) {
    $this->broker = $bp;
    $this->fileServer = $fsp;
  }

  /**
   * Upload the files to the fileserver and initiates evaluation on backend
   * @param JobConfig $jobConfig The submission configuration file content
   * @param array $files Paths to submitted files
   * @param array $headers Headers used to further specify which workers can evaluate the submission
   * @param string $hardwareGroup Hardware group to evaluate this submission with
   *                              (if none is given, all hardware groups associated with the assignment can be used)
   * @return NULL|string URL of the results when the submission was accepted and evaluation started, otherwise NULL
   * @throws SubmissionFailedException
   */
  public function initiateEvaluation(JobConfig $jobConfig, array $files, array $headers = [], string $hardwareGroup = NULL) {
    // firstly let us set address of fileserver to job configuration
    $jobConfig->setFileCollector($this->fileServer->getFileserverTasksUrl());

    // send all data to fileserver
    list($archiveUrl, $resultsUrl) = $this->fileServer->sendFiles(
      $jobConfig->getJobId(),
      (string) $jobConfig,
      $files
    );

    // tell broker that we have new job which has to be executed
    $evaluationStarted = $this->broker->startEvaluation(
      $jobConfig->getJobId(),
      $hardwareGroup !== NULL ? [$hardwareGroup] : $jobConfig->getHardwareGroups(),
      $headers,
      $archiveUrl,
      $resultsUrl
    );

    if ($evaluationStarted) {
      return $resultsUrl;
    } else {
      return NULL;
    }
  }


}
