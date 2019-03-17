<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * Occurs when everything goes south and application cannot perform
 * requested operation in a proper and expected way.
 */
class InternalServerException extends ApiException {
  /**
   * Create instance with further details.
   * @param string $details description
   */
  public function __construct(string $details = 'please contact the administrator of the service') {
    parent::__construct("Internal Server Error - $details", IResponse::S500_INTERNAL_SERVER_ERROR);
  }
}
