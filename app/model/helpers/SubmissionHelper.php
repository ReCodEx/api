<?php

namespace App\Model\Helpers;

use App\Model\Helpers\JobConfig\JobConfig;
use App\Model\Entity\Submission;

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
    list($archiveUrl, $resultsUrl) = $this->fileServer->sendFiles(
      $jobConfig->getJobId(),
      (string) $jobConfig,
      $files
    );
    
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
