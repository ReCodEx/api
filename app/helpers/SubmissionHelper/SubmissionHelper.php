<?php

namespace App\Helpers;

/**
 * Class which shohuld create submission, generate job configuration,
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
   * @todo
   */
  public function submit() {
    ;
  }


}
