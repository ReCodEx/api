<?php

namespace App\Exceptions;

class JobConfigLoadingException extends SubmissionFailedException {

  public function __construct($msg = 'Please contact your supervisor') {
    parent::__construct("Job configuration file cannot be opened or parsed - $msg");
  }

}
