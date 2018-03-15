<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * General exception used in all yaml or json loading.
 */
class ParseException extends ApiException {
  /**
   * Create instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Please contact system administrator') {
    parent::__construct("Parsing error - $msg", IResponse::S400_BAD_REQUEST);
  }
}
