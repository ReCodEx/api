<?php

namespace App\Helpers;

use App\Helpers\JobConfig\JobConfig;

class SubmissionHelper {

  /** @var BrokerProxy */
  private $broker;

  /** @var FileServerProxy */
  private $fileServer;

  public function __construct(BrokerProxy $bp, FileServerProxy $fsp) {
    $this->broker = $bp;
    $this->fileServer = $fsp;
  }

  /**
   * Uploads the files to the file server and initiates evaluation on backend.
   * @param JobConfig  The submission to evaluate
   * @return bool       True when the submission was accepted by the evaluation server, otherwise false.
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
