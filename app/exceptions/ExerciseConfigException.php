<?php

namespace App\Exceptions;

/**
 * General exception used in all exercise configuration helpers in case
 * of error. Usually concerning bad structure or bad value type.
 */
class ExerciseConfigException extends ApiException {
  /**
   * Create instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Please contact your supervisor') {
    parent::__construct("Exercise configuration error - $msg");
  }
}
