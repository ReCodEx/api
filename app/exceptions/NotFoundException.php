<?php

namespace App\Exception;

use Nette\Http\IResponse;

class NotFoundException extends ApiException {
  public function __construct() {
    parent::__construct('Not Found', IResponse::S404_NOT_FOUND);
  }
}
