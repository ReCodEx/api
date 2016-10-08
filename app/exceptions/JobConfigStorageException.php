<?php

namespace App\Exceptions;

class JobConfigStorageException extends ApiException {

  public function __construct($msg = 'Job config could not have been stored or loaded') {
    parent::__construct("Job configuration storage error - $msg");
  }

}
