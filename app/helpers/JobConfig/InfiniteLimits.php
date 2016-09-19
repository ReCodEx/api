<?php

namespace App\Helpers\JobConfig;

class InfiniteLimits extends Limits {

  const INFINITE_TIME = 99999999.9;
  const INFINITE_MEMORY = 99999999;

  public function __construct($id) {
    parent::__construct([
      "hw-group-id" => $id,
      "time" => self::INFINITE_TIME,
      "memory" => self::INFINITE_MEMORY
    ]);
  }

}
