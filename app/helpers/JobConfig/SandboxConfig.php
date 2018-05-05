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
  /** Output config key */
  const OUTPUT_KEY = "output";
  /** Carboncopy stdout key */
  const CARBONCOPY_STDOUT_KEY = "carboncopy-stdout";
  /** Carboncopy stderr key */
  const CARBONCOPY_STDERR_KEY = "carboncopy-stderr";
  /** Change directory key */
  const CHDIR_KEY = "chdir";
  /** Limits collection key */
  const LIMITS_KEY = "limits";

  /** @var string Sandbox name */
  private $name = "";
  /** @var string|null Standard input redirection file */
  private $stdin = null;
  /** @var string|null Standard output redirection file */
  private $stdout = null;
  /** @var string|null Standard error redirection file */
  private $stderr = null;
  /** @var bool Output from stdout and stderr will be written to result yaml */
  private $output = false;
  /** @var string|null Standard output carboncopy file */
  private $carboncopyStdout = null;
  /** @var string|null Standard error carboncopy file */
  private $carboncopyStderr = null;
  /** @var string|null Change directory */
  protected $chdir = null;
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
   * @return string|null
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
   * @return string|null
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
   * @return string|null
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
   * Get output to stdout and stderr.
   * @return bool
   */
  public function getOutput(): bool {
    return $this->output;
  }

  /**
   * Set output to stdout and stderr.
   * @param bool $output
   * @return $this
   */
  public function setOutput(bool $output) {
    $this->output = $output;
    return $this;
  }

  /**
   * Return standard output carboncopy file.
   * @return string|null
   */
  public function getCarboncopyStdout() {
    return $this->carboncopyStdout;
  }

  /**
   * Set output carboncopy file.
   * @param string $stdout
   * @return $this
   */
  public function setCarboncopyStdout($stdout) {
    $this->carboncopyStdout = $stdout;
    return $this;
  }

  /**
   * Get standard error carboncopy file.
   * @return string|null
   */
  public function getCarboncopyStderr() {
    return $this->carboncopyStderr;
  }

  /**
   * Set error carboncopy file.
   * @param string $stderr
   * @return $this
   */
  public function setCarboncopyStderr($stderr) {
    $this->carboncopyStderr = $stderr;
    return $this;
  }

  /**
   * Get directory in which sandboxed program will be executed.
   * @return string|null
   */
  public function getChdir() {
    return $this->chdir;
  }

  /**
   * Set directory to which sandbox will change working directory.
   * @param string $chdir working directory
   * @return $this
   */
  public function setChdir($chdir) {
    $this->chdir = $chdir;
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
    if ($this->output) { $data[self::OUTPUT_KEY] = $this->output; }
    if (!empty($this->carboncopyStdout)) { $data[self::CARBONCOPY_STDOUT_KEY] = $this->carboncopyStdout; }
    if (!empty($this->carboncopyStderr)) { $data[self::CARBONCOPY_STDERR_KEY] = $this->carboncopyStderr; }
    if (!empty($this->chdir)) { $data[self::CHDIR_KEY] = $this->chdir; }

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
