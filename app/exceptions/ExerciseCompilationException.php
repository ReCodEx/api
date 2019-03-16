<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Exception used in exercise compilation to job configuration. Used on internal
 * errors during compilation.
 */
class ExerciseCompilationException extends ApiException {

  /**
   * Create instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Please contact system administrator') {
    parent::__construct("Exercise compilation error - $msg", IResponse::S500_INTERNAL_SERVER_ERROR);
  }
}
