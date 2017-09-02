<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\MalformedJobConfigException;
use Nette\Utils\Arrays;
use Symfony\Component\Yaml\Yaml;


/**
 * Sandbox configuration holder contains mainly name of used sandbox and limits
 * for specific hardware groups. Limits can be also removed or set
 * to another ones.
 */
class SandboxConfig {
  /** Sandbox name key */
  const NAME_KEY = "name";
  /** Stdin config key */
  const STDIN_KEY = "stdin";
  /** Stdout config key */
  const STDOUT_KEY = "stdout";
  /** Stderr config key */
  const STDERR_KEY = "stderr";
  /** Limits collection key */
  const LIMITS_KEY = "limits";

  /** @var string Sandbox name */
  private $name = "";
  /** @var string|NULL Standard input redirection file */
  private $stdin = NULL;
  /** @var string|NULL Standard output redirection file */
  private $stdout = NULL;
  /** @var string|NULL Standard error redirection file */
  private $stderr = NULL;
  /** @var array List of limits */
  private $limits = [];
  /** @var array Additional data */
  private $data = [];

  /**
   * Get sandbox name.
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Set name of the used sandbox.
   * @param string $name
   * @return $this
   */
  public function setName(string $name) {
    $this->name = $name;
    return $this;
  }

  /**
   * Return standard input redirection file.
   * @return string|NULL
   */
  public function getStdin() {
    return $this->stdin;
  }

  /**
   * Set input redirection file.
   * @param string $stdin
   * @return $this
   */
  public function setStdin($stdin) {
    $this->stdin = $stdin;
    return $this;
  }

  /**
   * Return standard output redirection file.
   * @return string|NULL
   */
  public function getStdout() {
    return $this->stdout;
  }

  /**
   * Set output redirection file.
   * @param string $stdout
   * @return $this
   */
  public function setStdout($stdout) {
    $this->stdout = $stdout;
    return $this;
  }

  /**
   * Get standard error redirection file.
   * @return string|NULL
   */
  public function getStderr() {
    return $this->stderr;
  }

  /**
   * Set error redirection file.
   * @param string $stderr
   * @return $this
   */
  public function setStderr($stderr) {
    $this->stderr = $stderr;
    return $this;
  }

  /**
   * Gets limits as array.
   * @return Limits[]
   */
  public function getLimitsArray(): array {
    return $this->limits;
  }

  /**
   * Does the task config have limits for given hardware group?
   * @param string $hardwareGroupId identification of hardware group
   * @return bool
   */
  public function hasLimits(string $hardwareGroupId): bool {
    return isset($this->limits[$hardwareGroupId]);
  }

  /**
   * Get the configured limits for a specific hardware group.
   * @param string $hardwareGroupId Hardware group ID
   * @return Limits|null Limits for the specified hardware group
   */
  public function getLimits(string $hardwareGroupId): ?Limits {
    return Arrays::get($this->limits, $hardwareGroupId, null);
  }

  /**
   * Set limits for a specific hardware group
   * @param Limits|null $limits            The limits
   * @return void
   */
  public function setLimits(?Limits $limits) {
    if (!$limits) {
      return;
    }
    $this->limits[$limits->getId()] = $limits;
  }

  /**
   * Set limits of a given HW group to undefined, which basically means
   * that there are no more limits anymore.
   * @param string $hardwareGroupId   Hardware group ID
   * @return void
   */
  public function removeLimits(string $hardwareGroupId) {
    $this->setLimits(new UndefinedLimits($hardwareGroupId));
  }

  /**
   * Get additional data.
   * Needed for forward compatibility.
   * @return array
   */
  public function getAdditionalData(): array {
    return $this->data;
  }

  /**
   * Set additional data, which cannot be parsed into structure.
   * Needed for forward compatibility.
   * @param array $data
   * @return $this
   */
  public function setAdditionalData(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::NAME_KEY] = $this->name;
    if (!empty($this->stdin)) { $data[self::STDIN_KEY] = $this->stdin; }
    if (!empty($this->stdout)) { $data[self::STDOUT_KEY] = $this->stdout; }
    if (!empty($this->stderr)) { $data[self::STDERR_KEY] = $this->stderr; }

    if (!empty($this->limits)) {
      $data[self::LIMITS_KEY] = [];
      foreach ($this->limits as $limit) {
        $data[self::LIMITS_KEY][] = $limit->toArray();
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

}
