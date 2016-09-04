<?php

namespace App\Helpers\JobConfig;


class ExecutionTaskConfig extends TaskConfig {

  private $limits;
    
  public function __construct(array $data) {
    parent::__construct($data);
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

    return NULL;
  }

}
