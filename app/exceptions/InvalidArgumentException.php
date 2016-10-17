<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * Used if there is something very wrong with some particular parameter.
 * It may be wrong type or missing argument or something similar.
 */
class InvalidArgumentException extends ApiException {
  /**
   * Creates exception with invalid argument name and some further description.
   * @param string $arg name of argument
   * @param string $msg further message
   */
  public function __construct(string $arg, string $msg = 'check the API documentation for more information about validation rules') {
    parent::__construct("Invalid Argument '$arg' - $msg", IResponse::S400_BAD_REQUEST);
  }
}
