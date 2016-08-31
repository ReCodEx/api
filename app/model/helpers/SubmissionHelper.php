<?php

namespace App\Model\Helpers;

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
   * @param Submission  The submission to evaluate
   * @return bool       True when the submission was accepted by the evaluation server, otherwise false.
   */
  public function initiateEvaluation(Submission $submission) {
    list($archiveUrl, $resultsUrl) = $this->fileServer->sendFiles(
      $submission->getId(),
      $submission->getJobConfig(),
      $submission->getFiles()->toArray()
    );
    
    // save the results URL to this entity
    $submission->setResultsUrl($resultsUrl);
    $hardwareGroup = $submission->getHardwareGroup();

    return $this->broker->startEvaluation(
      $submission->getId(),
      $submission->getHardwareGroup(),
      $archiveUrl,
      $resultsUrl
    );
  }


}
