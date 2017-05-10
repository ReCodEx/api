<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration exercise limits holder, limits indexed by box-id.
 */
class ExerciseLimits implements JsonSerializable {

  /** @var array limits indexed by box identification */
  protected $limits = array();


  /**
   * Get associative array of limits.
   * @return array
   */
  public function getLimits(): array {
    return $this->limits;
  }

  /**
   * Add limits for appropriate task into this holder.
   * @param string $boxId identification of box to which limits belongs to
   * @param Limits $limits limits
   * @return $this
   */
  public function addLimits(string $boxId, Limits $limits): ExerciseLimits {
    $this->limits[$boxId] = $limits;
    return $this;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];
    foreach ($this->limits as $key => $value) {
      $data[$key] = $value->toArray();
    }
    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

  /**
   * Enable automatic serialization to JSON
   * @return array
   */
  public function jsonSerialize() {
    return $this->toArray();
  }
}
