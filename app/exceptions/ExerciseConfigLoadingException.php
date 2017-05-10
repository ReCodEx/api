<?php

namespace App\Exceptions;

/**
 * General exception used in all exercise configuration helpers in case
 * of loading error. Ussually concerning bad structure or bad value type.
 */
class ExerciseConfigLoadingException extends ApiException {
  /**
   * Create instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Please contact your supervisor') {
    parent::__construct("Exercise configuration cannot be properly parsed - $msg");
  }
}
