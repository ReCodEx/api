<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * General exception used in all exercise configuration helpers in case
 * of error. Usually concerning bad structure or bad value type.
 */
class ExerciseConfigException extends ApiException {
  /**
   * Create instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Please contact system administrator') {
    parent::__construct("Exercise configuration error - $msg", IResponse::S400_BAD_REQUEST);
  }
}
