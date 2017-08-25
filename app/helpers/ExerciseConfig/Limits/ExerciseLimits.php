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
   * @return array
   */
  public function getLimitsArray(): array {
    return $this->limits;
  }

  /**
   * Get limits for the given identifications.
   * @param string $testId
   * @param string $pipelineId
   * @param string $boxId
   * @return Limits|null
   */
  public function getLimits(string $testId, string $pipelineId, string $boxId): ?Limits {
    return Arrays::get($this->limits, [$testId, $pipelineId, $boxId], null);
  }

  /**
   * Add limits for appropriate task into this holder.
   * @param string $testId
   * @param string $pipelineId
   * @param string $boxId identification of box to which limits belongs to
   * @param Limits $limits limits
   * @return ExerciseLimits
   */
  public function addLimits(string $testId, string $pipelineId, string $boxId, Limits $limits): ExerciseLimits {
    if (!array_key_exists($testId, $this->limits)) {
      $this->limits[$testId] = array();
    }
    if (!array_key_exists($pipelineId, $this->limits[$testId])) {
      $this->limits[$testId][$pipelineId] = array();
    }

    $this->limits[$testId][$pipelineId][$boxId] = $limits;
    return $this;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];
    foreach ($this->limits as $testId => $testVal) {
      $data[$testId] = array();
      foreach ($testVal as $pipelineId => $pipelineVal) {
        $data[$testId][$pipelineId] = array();
        foreach ($pipelineVal as $boxId => $boxVal) {
          $data[$testId][$pipelineId][$boxId] = $boxVal->toArray();
        }
      }
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
