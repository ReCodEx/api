<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\SandboxConfig;
use Symfony\Component\Yaml\Yaml;


/**
 * Extends TaskBase holder and adds/contains mainly sandbox configuration.
 * Represents tasks which are supposed to run in sandboxed environment.
 */
class ExternalTask extends TaskBase {

  /** Sandbox config key */
  const SANDBOX_KEY = "sandbox";

  /** @var SandboxConfig Sandbox configuration */
  private $sandboxConfig;

  /**
   * Parse external task from given structured data.
   * @param array $data structured config
   * @throws JobConfigLoadingException
   */
  public function __construct(array $data) {
    parent::__construct($data);

    if (!isset($this->data[self::SANDBOX_KEY])) {
      throw new JobConfigLoadingException("External task '{$this->getId()}' does not define field '" . self::SANDBOX_KEY . "'");
    }

    $this->sandboxConfig = new SandboxConfig($this->data[self::SANDBOX_KEY]);
    unset($this->data[self::SANDBOX_KEY]);
  }

  /**
   * Get sandbox configuration which will be used for execution.
   * @return string Description
   */
  public function getSandboxConfig(): SandboxConfig {
    return $this->sandboxConfig;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    return array_merge(
      parent::toArray(),
      [ "sandbox" => $this->sandboxConfig->toArray() ]
    );
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

}
