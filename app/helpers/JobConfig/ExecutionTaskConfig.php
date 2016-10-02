<?php

namespace App\Helpers\JobConfig;
use Symfony\Component\Yaml\Yaml;
use App\Exceptions\JobConfigLoadingException;

class ExecutionTaskConfig extends TaskConfig {

  /** @var Limits[] */
  private $limits = [];

  /** @var array */
  private $limitsConfig;

  public function __construct(array $data) {
    parent::__construct($data);
    if (!isset($data["sandbox"]) || !isset($data["sandbox"]["limits"])) {
      throw new JobConfigLoadingException("Execution task '{$this->getId()}' does not define limits for the sandbox.");
    }

    $this->limitsConfig = $data["sandbox"]["limits"];
  }

  /**
   * Get the configured limits for a specific hardware group.
   * @param  string $hardwareGroupId Hardware group ID
   * @return Limits Limits for the specified hardware group
   */
  public function getLimits(string $hardwareGroupId): Limits {
    if (!isset($this->limits[$hardwareGroupId])) {
      $limits = $this->findLimits($hardwareGroupId);
      $this->setLimits($hardwareGroupId, new Limits($limits));
    }

    return $this->limits[$hardwareGroupId];
  }

  /**
   * Return configuration for a specific hardware group or throw an exception,
   * if there is no config.
   * @param string $hardwareGroupId
   * @throws JobConfigLoadingException
   * @return array
   */
  private function findLimits(string $hardwareGroupId): array {
    foreach ($this->limitsConfig as $limits) {
      if ($limits["hw-group-id"] === $hardwareGroupId) {
        return $limits;
      }
    }

    throw new JobConfigLoadingException("Execution task '{$this->getId()}' does not define limits for hardware group '$hardwareGroupId'");
  }

  /**
   * Set limits for a specific hardware group
   * @param string $hardwareGroupId   Hardware group ID
   * @param Limits $limits            The limits
   * @return void
   */
  public function setLimits(string $hardwareGroupId, Limits $limits) {
    $this->limits[$hardwareGroupId] = $limits;
  }

  /**
   * Set limits of a given HW group to infinite, which basically means
   * that there are no more limits anymore.
   * @param string $hardwareGroupId   Hardware group ID
   * @return void
   */
  public function removeLimits(string $hardwareGroupId) {
    $this->setLimits($hardwareGroupId, new InfiniteLimits($hardwareGroupId));
  }

  /**
   * Merge all the data of the parent with all the
   * @return array
   */
  public function toArray() {
    return array_merge(
      parent::toArray(),
      [
        "sandbox" => [
          "limits" => $this->getLimitsConfig()
        ]
      ]
    );
  }

  /**
   * Get merged limits of new, altered and original configuration of limits.
   * @return array
   */
  private function getLimitsConfig(): array {
    $data = array_map(
      function ($limits) {
        return $limits->toArray();
      },
      array_values($this->limits)
    );

    // add original data for all the uncached limits
    // (definitelly unaltered)
    foreach ($this->getAllHWGroupsIds() as $id) {
      if (!isset($this->limits[$id])) {
        $data[] = $this->findLimits($id);
      }
    }

    return $data;
  }

  /**
   * Get array of all hardware groups from the input data
   * @return array
   */
  private function getAllHWGroupsIds() {
    return array_map(
      function($cfg) {
        return $cfg['hw-group-id'];
      },
      $this->limitsConfig
    );
  }

}
