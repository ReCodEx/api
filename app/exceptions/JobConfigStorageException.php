<?php

namespace App\Exceptions;

/**
 * Job configuration Storage helper uses this exception to express
 * tiny major errors.
 */
class JobConfigStorageException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct($msg = 'Job config could not have been stored or loaded') {
    parent::__construct("Job configuration storage error - $msg");
  }

}
