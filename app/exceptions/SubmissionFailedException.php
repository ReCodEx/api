<?php

namespace App\Exception;
use Nette\Http\IResponse;

class SubmissionFailedException extends ApiException {

  public function __construct($msg = 'Unexpected server error') {
    parent::__construct("Submission Failed - $msg", IResponse::S500_INTERNAL_SERVER_ERROR);
  }

}
