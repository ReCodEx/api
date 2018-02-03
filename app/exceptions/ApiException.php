<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * The great grandfather of almost all exceptions which can occur
 * in whole application. Has same means of constructions
 * as the greatest exception, the native one.
 */
class ApiException extends \Exception {

  /**
   * Classic php exception constructor.
   * @param string    $msg      Error message
   * @param int       $code     Error code
   * @param \Exception $previous Previous exception
   */
  public function __construct($msg = "Unexpected API error", $code = IResponse::S500_INTERNAL_SERVER_ERROR, $previous = null) {
    parent::__construct($msg, $code, $previous);
  }

  /**
   * Gets additional headers which should be added into http response.
   * @return array
   */
  public function getAdditionalHttpHeaders() {
    return [];
  }

}
