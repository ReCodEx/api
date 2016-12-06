<?php

namespace App\Helpers\JobConfig;


/**
 * Special structure extending Limits structure. None of the limits is defined,
 * only hw-group-id option is needed at construction.
 */
class UndefinedLimits extends Limits {

  /**
   * Construct limits only with given hardware group identification.
   * @param type $id
   */
  public function __construct(string $id) {
    $this->setId($id);
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    return [
        "hw-group-id" => $this->id
    ];
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

}
