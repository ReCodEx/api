<?php

namespace App\Helpers\JobConfig;


/**
 *
 */
class UndefinedLimits extends Limits {

  /**
   *
   * @param type $id
   */
  public function __construct(string $id) {
    parent::__construct([
      "hw-group-id" => $id
    ]);
  }

  /**
   *
   * @return array
   */
  public function toArray(): array {
    return [
        "hw-group-id" => $this->id
    ];
  }

  /**
   *
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

}
