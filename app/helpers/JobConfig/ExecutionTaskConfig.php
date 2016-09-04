<?php

namespace App\Helpers\JobConfig;
use App\Exception\JobConfigLoadingException;

class ExecutionTaskConfig extends TaskConfig {

  private $limits;
    
  public function __construct(array $data) {
    parent::__construct($data);
    if (!isset($data["sandbox"]) || !isset($data["sandbox"]["limits"])) {
      throw new JobConfigLoadingException("Execution task '{$this->getId()}' does not define limits for the sandbox.");
    }

    $this->limits = $data["sandbox"]["limits"];
  }

  /**
   * Get the configured limits for a specific hardware group.
   * @param  string $hardwareGroupId Hardware group ID
   * @return Limits Limits for the specified hardware group
   */
  public function getLimits($hardwareGroupId): Limits {
    foreach ($this->limits as $limits) {
      if ($limits["hw-group-id"] === $hardwareGroupId) {
        return new Limits($limits);
      }
    }

    throw new JobConfigLoadingException("Execution task '{$this->getId()}' does not define limits for hardware group '$hardwareGroupId'");
  }

}
