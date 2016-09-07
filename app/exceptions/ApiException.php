<?php

namespace App\Exception;

class ApiException extends \Exception {

  /**
   * @param string    $msg      Error message
   * @param int       $code     Error code
   * @param Exception $previous Previous exception
   */ 
  public function __construct($msg = "Unexpected API error", $code = 500, $previous = NULL) {
    parent::__construct($msg, $code, $previous);
  }

  public function getAdditionalHttpHeaders() {
    return [];
  }

}
