<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;


/**
 * BoundDirectory helper holder structure.
 */
class BoundDirectoryConfig {
  /** Source folder key */
  const SRC_KEY = "src";
  /** Destination folder key */
  const DST_KEY = "dst";
  /** Mode key */
  const MODE_KEY = "mode";

  /** @var string Source folder for bound directory */
  private $source;
  /** @var string Destination folder for bound directory */
  private $destination;
  /** @var string Mode in which folder is loaded */
  private $mode;
  /** @var array Additional data */
  private $data;

  /**
   * Construct BoundDirectory from given structured configuration.
   * @param array $data Structured configuration
   * @throws JobConfigLoadingException In case of any parsing error
   */
  public function __construct(array $data) {

    if (!isset($data[self::SRC_KEY])) {
      throw new JobConfigLoadingException("Bound directory does not contain required field '" . self::SRC_KEY . "'");
    }
    $this->source = $data[self::SRC_KEY];
    unset($data[self::SRC_KEY]);

    if (!isset($data[self::DST_KEY])) {
      throw new JobConfigLoadingException("Bound directory does not contain required field '" . self::DST_KEY . "'");
    }
    $this->destination = $data[self::DST_KEY];
    unset($data[self::DST_KEY]);

    if (!isset($data[self::MODE_KEY])) {
      throw new JobConfigLoadingException("Bound directory does not contain required field '" . self::MODE_KEY . "'");
    }
    $this->mode = $data[self::MODE_KEY];
    unset($data[self::MODE_KEY]);

    // *** LOAD REMAINING DATA
    $this->data = $data;
  }

  /**
   * Get source folder for bound directory.
   * @return string
   */
  public function getSource(): string {
    return $this->source;
  }

  /**
   * Get destination folder for source directory.
   * @return string
   */
  public function getDestination(): string {
    return $this->destination;
  }

  /**
   * Get mounting mode of bounded directory.
   * @return string
   */
  public function getMode(): string {
    return $this->mode;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::SRC_KEY] = $this->source;
    $data[self::DST_KEY] = $this->destination;
    $data[self::MODE_KEY] = $this->mode;
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

/**
 * Limits helper holder structure which holds every possible information about
 * limits and keep forward compatibility.
 */
class Limits {
  /** Hardware group identification key */
  const HW_GROUP_ID_KEY = "hw-group-id";
  /** Time limit key */
  const TIME_KEY = "time";
  /** Wall time limit key */
  const WALL_TIME_KEY = "wall-time";
  /** Extra time limit key */
  const EXTRA_TIME_KEY = "extra-time";
  /** Stack size limit key */
  const STACK_SIZE_KEY = "stack-size";
  /** Memory limit key */
  const MEMORY_KEY = "memory";
  /** Parallel executions key */
  const PARALLEL_KEY = "parallel";
  /** Disk size key */
  const DISK_SIZE_KEY = "disk-size";
  /** Disk files key */
  const DISK_FILES_KEY = "disk-files";
  /** Environmental variables key */
  const ENVIRON_KEY = "environ-variable";
  /** Change directory key */
  const CHDIR_KEY = "chdir";
  /** Bound directories key */
  const BOUND_DIRECTORIES_KEY = "bound-directories";

  /** @var string ID of the hardware group */
  protected $id;
  /** @var float Time limit */
  protected $time = 0;
  /** @var float Wall time limit */
  protected $wallTime = 0;
  /** @var float Extra time limit */
  protected $extraTime = 0;
  /** @var int Stack size limit */
  protected $stackSize = 0;
  /** @var int Memory limit */
  protected $memory = 0;
  /** @var int Parallel processes/threads count limit */
  protected $parallel = 0;
  /** @var int Disk size limit */
  protected $diskSize = 0;
  /** @var int Disk files limit */
  protected $diskFiles = 0;
  /** @var string[] Environmental variables array */
  protected $environVariables = [];
  /** @var string Change directory */
  protected $chdir = NULL;
  /** @var BoundDirectoryConfig[] Bound directories array */
  protected $boundDirectories = [];
  /** @var array Additional data */
  protected $data;

  /**
   * Construct Limits structure from given structured configuration.
   * @param array $data Structured config
   * @throws JobConfigLoadingException In case of parsing error
   */
  public function __construct(array $data) {

    if (!isset($data[self::HW_GROUP_ID_KEY])) {
      throw new JobConfigLoadingException("Sandbox limits section does not contain required field '" . self::HW_GROUP_ID_KEY . "'");
    }
    $this->id = $data[self::HW_GROUP_ID_KEY];
    unset($data[self::HW_GROUP_ID_KEY]);

    // *** LOAD OPTIONAL DATAS

    if (isset($data[self::TIME_KEY])) {
      $this->time = floatval($data[self::TIME_KEY]);
      unset($data[self::TIME_KEY]);
    }

    if (isset($data[self::WALL_TIME_KEY])) {
      $this->wallTime = floatval($data[self::WALL_TIME_KEY]);
      unset($data[self::WALL_TIME_KEY]);
    }

    if (isset($data[self::EXTRA_TIME_KEY])) {
      $this->extraTime = floatval($data[self::EXTRA_TIME_KEY]);
      unset($data[self::EXTRA_TIME_KEY]);
    }

    if (isset($data[self::STACK_SIZE_KEY])) {
      $this->stackSize = intval($data[self::STACK_SIZE_KEY]);
      unset($data[self::STACK_SIZE_KEY]);
    }

    if (isset($data[self::MEMORY_KEY])) {
      $this->memory = intval($data[self::MEMORY_KEY]);
      unset($data[self::MEMORY_KEY]);
    }

    if (isset($data[self::PARALLEL_KEY])) {
      $this->parallel = intval($data[self::PARALLEL_KEY]);
      unset($data[self::PARALLEL_KEY]);
    }

    if (isset($data[self::DISK_SIZE_KEY])) {
      $this->diskSize = intval($data[self::DISK_SIZE_KEY]);
      unset($data[self::DISK_SIZE_KEY]);
    }

    if (isset($data[self::DISK_FILES_KEY])) {
      $this->diskFiles = intval($data[self::DISK_FILES_KEY]);
      unset($data[self::DISK_FILES_KEY]);
    }

    if (isset($data[self::ENVIRON_KEY]) && is_array($data[self::ENVIRON_KEY])) {
      $this->environVariables = $data[self::ENVIRON_KEY];
      unset($data[self::ENVIRON_KEY]);
    }

    if (isset($data[self::CHDIR_KEY])) {
      $this->chdir = strval($data[self::CHDIR_KEY]);
      unset($data[self::CHDIR_KEY]);
    }

    if (isset($data[self::BOUND_DIRECTORIES_KEY]) && is_array($data[self::BOUND_DIRECTORIES_KEY])) {
      foreach ($data[self::BOUND_DIRECTORIES_KEY] as $dir) {
        $this->boundDirectories[] = new BoundDirectoryConfig($dir);
      }
      unset($data[self::BOUND_DIRECTORIES_KEY]);
    }

    // *** LOAD REMAINING INFO

    $this->data = $data;
  }

  /**
   * Hardware group identification.
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Returns the time limit in seconds.
   * @return float Number of seconds
   */
  public function getTimeLimit(): float {
    return $this->time;
  }

  /**
   * Returns wall time limit.
   * @return float Number of seconds
   */
  public function getWallTime(): float {
    return $this->wallTime;
  }

  /**
   * Returns extra time limit.
   * @return float Number of seconds
   */
  public function getExtraTime(): float {
    return $this->extraTime;
  }

  /**
   * Get maximum stack size.
   * @return int Number in kilobytes
   */
  public function getStackSize(): int {
    return $this->stackSize;
  }

  /**
   * Returns the memory limit in bytes
   * @return int Number of kilobytes
   */
  public function getMemoryLimit(): int {
    return $this->memory;
  }

  /**
   * Gets number of processes/threads which can be created in sandboxed program.
   * @return int Number of processes/threads
   */
  public function getParallel(): int {
    return $this->parallel;
  }

  /**
   * Returns maximum disk IO operations count in kilobytes.
   * @return int Number in kilobytes
   */
  public function getDiskSize(): int {
    return $this->diskSize;
  }

  /**
   * Gets maximum number of opened files in sandboxed application.
   * @return int Number of files
   */
  public function getDiskFiles(): int {
    return $this->diskFiles;
  }

  /**
   * Get array of environmental variables which will be set in sandbox.
   * @return array
   */
  public function getEnvironVariables(): array {
    return $this->environVariables;
  }

  /**
   * Get directory in which sandboxed program will be executed.
   * @return string|NULL
   */
  public function getChdir() {
    return $this->chdir;
  }

  /**
   * Gets array of BoundDirectory structures representing bound directories
   * inside sandbox.
   * @return array
   */
  public function getBoundDirectories(): array {
    return $this->boundDirectories;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::HW_GROUP_ID_KEY] = $this->id;
    if ($this->time > 0) { $data[self::TIME_KEY] = $this->time; }
    if ($this->wallTime > 0) { $data[self::WALL_TIME_KEY] = $this->wallTime; }
    if ($this->extraTime > 0) { $data[self::EXTRA_TIME_KEY] = $this->extraTime; }
    if ($this->stackSize > 0) { $data[self::STACK_SIZE_KEY] = $this->stackSize; }
    if ($this->memory) { $data[self::MEMORY_KEY] = $this->memory; }
    if ($this->parallel > 0) { $data[self::PARALLEL_KEY] = $this->parallel; }
    if ($this->diskSize > 0) { $data[self::DISK_SIZE_KEY] = $this->diskSize; }
    if ($this->diskFiles > 0) { $data[self::DISK_FILES_KEY] = $this->diskFiles; }
    if (!empty($this->environVariables)) { $data[self::ENVIRON_KEY] = $this->environVariables; }
    if (!empty($this->chdir)) { $data[self::CHDIR_KEY] = $this->chdir; }

    $data[self::BOUND_DIRECTORIES_KEY] = [];
    foreach ($this->boundDirectories as $dir) {
      $data[self::BOUND_DIRECTORIES_KEY][] = $dir->toArray();
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
