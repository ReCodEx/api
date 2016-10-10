<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class Limits {

  /** @var array Raw data */
  private $data;

  /** @var string ID of the harwdare group */
  protected $id;

  /** @var float Time limit */
  protected $timeLimit = 0;

  /** @var int Memory limit */
  protected $memoryLimit = 0;

  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data["hw-group-id"])) {
      throw new JobConfigLoadingException("Sandbox limits section does not contain required field 'hw-group-id'");
    }

    if (!isset($data["time"])) {
      throw new JobConfigLoadingException("Sandbox limits section does not contain required time limit (field 'time')");
    }

    if (!isset($data["memory"])) {
      throw new JobConfigLoadingException("Sandbox limits section does not contain required memory limit (field 'memory')");
    }

    $this->id = $data["hw-group-id"];
    $this->timeLimit = floatval($data["time"]);
    $this->memoryLimit = intval($data["memory"]);
  }

  public function getId() {
    return $this->id;
  }

  /**
   * Returns the time limit in milliseconds
   * @return int Number of milliseconds
   */
  public function getTimeLimit(): float {
    return $this->timeLimit;
  }

  /**
   * Returns the memory limit in bytes
   * @return int Number of bytes
   */
  public function getMemoryLimit(): int {
    return $this->memoryLimit;
  }

  public function toArray() {
    return $this->data;
  }

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
