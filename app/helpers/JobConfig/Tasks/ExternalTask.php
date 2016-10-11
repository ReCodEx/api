<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\SandboxConfig;
use Symfony\Component\Yaml\Yaml;


/**
 *
 */
class ExternalTask extends TaskBase {

  const SANDBOX_KEY = "sandbox";

  /** @var SandboxConfig */
  private $sandboxConfig;

  /**
   *
   * @param array $data
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
   * Get sandbox configuration which will be used for execution
   * @return string Description
   */
  public function getSandboxConfig(): SandboxConfig {
    return $this->sandboxConfig;
  }

  /**
   * Merge all the data of the parent with all the
   * @return array
   */
  public function toArray() {
    return array_merge(
      parent::toArray(),
      [ "sandbox" => $this->sandboxConfig->toArray() ]
    );
  }

  /**
   * Serialize the config
   * @return string
   */
  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
