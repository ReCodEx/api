<?php

namespace App\Helpers\JobConfig;

class UndefinedLimits extends Limits {

  public function __construct($id) {
    parent::__construct([
      "hw-group-id" => $id
    ]);
  }

  public function toArray() {
    return [
        "hw-group-id" => $this->id
    ];
  }

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
