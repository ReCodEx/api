<?php

namespace App\Helpers;

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
   * @param JobConfig $jobConfig     The submission configuration file content
   * @param array     $files         Paths to submitted files
   * @param string    $hardwareGroup Harware group to evaluate this submission with
   * @return string|NULL  URL of the results when the submission was accepted and evaluation started, otherwise NULL
   * @throws SubmissionFailedException if the job cannot be submitted
   */
  public function initiateEvaluation(JobConfig $jobConfig, array $files, string $hardwareGroup) {
    // firstly let us set address of fileserver to job configuration
    $jobConfig->setFileCollector($this->fileServer->getFileserverTasksUrl());

    // send all datas to fileserver
    list($archiveUrl, $resultsUrl) = $this->fileServer->sendFiles(
      $jobConfig->getJobId(),
      (string) $jobConfig,
      $files
    );

    // tell broker that we have new job which has to be executed
    $evaluationStarted = $this->broker->startEvaluation(
      $jobConfig->getJobId(),
      $hardwareGroup,
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
