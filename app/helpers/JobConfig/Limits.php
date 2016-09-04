<?php

namespace App\Helpers\JobConfig;

class Limits {
    
  /** @var string ID of the harwdare group */
  private $id;

  /** @var array Raw data from the config of the limits */
  private $data;

  public function __construct(array $data) {
    $this->id = $data["hw-group-id"];
    $this->data = $data;
  }

  public function getId() {
    return $this->id;
  }

  /**
   * Returns the time limit in milliseconds
   * @return int Number of milliseconds
   */
  public function getTimeLimit(): float {
    return floatval($this->data["time"]);
  }

  /**
   * Returns the memory limit in bytes
   * @return int Number of bytes
   */
  public function getMemoryLimit(): int {
    return $this->data["memory"];
  }

}
