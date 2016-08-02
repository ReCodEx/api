<?php

namespace App\Exception;

class MalformedJobConfigException extends SubmissionFailedException {

  public function __construct($msg = 'Please contact your supervisor') {
    parent::__construct("Job configuration file is malformed - $msg");
  }

}
