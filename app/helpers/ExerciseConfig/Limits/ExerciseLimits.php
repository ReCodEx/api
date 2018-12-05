<?php

namespace App\Helpers\ExerciseConfig;
use Nette\Utils\Arrays;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration exercise limits holder, limits indexed by box-id.
 */
class ExerciseLimits implements JsonSerializable {

  /**
   * @var array limits indexed by (in order) test id, environment id,
   * pipeline id and box identification
   */
  protected $limits = array();


  /**
   * Get associative array of limits.
   * @return Limits[]
   */
  public function getLimitsArray(): array {
    return $this->limits;
  }

  /**
   * Get limits for the given identifications.
   * @param string $testId
   * @return Limits|null
   */
  public function getLimits(string $testId): ?Limits {
    return Arrays::get($this->limits, $testId, null);
  }

  /**
   * Add limits for appropriate task into this holder.
   * @param string $testId
   * @param Limits $limits limits
   * @return ExerciseLimits
   */
  public function addLimits(string $testId, Limits $limits): ExerciseLimits {
    $this->limits[$testId] = $limits;
    return $this;
  }

  /**
   * Remove limits for given test identification.
   * @param string $testId
   * @return ExerciseLimits
   */
  public function removeLimits(string $testId): ExerciseLimits {
    unset($this->limits[$testId]);
    return $this;
  }

  /**
   * Remove limits for given test identification.
   * @param string $testId
   * @return ExerciseLimits
   */
  public function changeTestId(string $oldId, string $newId): ExerciseLimits {
    $limits = $this->getLimits($oldId);
    if ($limits) {
      if ($this->getLimits($newId)) {
        throw new \Exception("Serious internal error. Newly created test ID is already present in exercise limits!");
      }
      $this->removeLimits($oldId)->addLimits($newId, $limits);
    }
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];
    foreach ($this->limits as $testId => $limits) {
      $data[$testId] = $limits->toArray();
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
