<?php

namespace App\Helpers\ExerciseConfig;
use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;

/**
 * Represents pipeline which contains list of boxes.
 */
class Pipeline implements JsonSerializable
{
  /**
   * @var array
   */
  protected $boxes = array();

  /**
   * True if internal list contains box identified with given key.
   * @param string $key
   * @return bool
   */
  public function contains(string $key): bool {
    return array_key_exists($key, $this->boxes);
  }

  /**
   * Returns box with specified key, if there is none, return null.
   * @param string $key
   * @return Box|null
   */
  public function get(string $key): ?Box {
    if (array_key_exists($key, $this->boxes)) {
      return $this->boxes[$key];
    }

    return null;
  }

  /**
   * If list contains box with the same name as the given one, original box
   * is replaced by the new one.
   * @param Box $box
   * @return Pipeline
   */
  public function set(Box $box): Pipeline {
    $this->boxes[$box->getName()] = $box;
    return $this;
  }

  /**
   * Remove box with given key.
   * @param string $key
   * @return Pipeline
   */
  public function remove(string $key): Pipeline {
    unset($this->boxes[$key]);
    return $this;
  }

  /**
   * Return count of the boxes in this pipeline.
   * @return int
   */
  public function size(): int {
    return count($this->boxes);
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];
    foreach ($this->boxes as $value) {
      $data[] = $value->toArray();
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
