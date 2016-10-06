<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

class CASMissingInfoException extends ApiException {
  public function __construct(string $msg = "Reading LDAP attribute failed") {
    parent::__construct($msg);
  }
}
