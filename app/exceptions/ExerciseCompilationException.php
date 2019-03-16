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
   * @param int $code
   */
  public function __construct(string $msg = 'Please contact system administrator', $code = IResponse::S500_INTERNAL_SERVER_ERROR) {
    parent::__construct("Exercise compilation error - $msg", $code);
  }
}
