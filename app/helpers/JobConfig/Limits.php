<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class BoundDirectoryConfig {

  const SRC_KEY = "src";
  const DST_KEY = "dst";
  const MODE_KEY = "mode";

  /** @var string */
  private $source;

  /** @var string */
  private $destination;

  /** @var string */
  private $mode;

  /** @var array */
  private $data;

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

  public function getSource(): string {
    return $this->source;
  }

  public function getDestination(): string {
    return $this->destination;
  }

  public function getMode(): string {
    return $this->mode;
  }

  public function toArray() {
    $data = $this->data;
    $data[self::SRC_KEY] = $this->source;
    $data[self::DST_KEY] = $this->destination;
    $data[self::MODE_KEY] = $this->mode;
    return $data;
  }

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}

class Limits {

  const HW_GROUP_ID_KEY = "hw-group-id";
  const TIME_KEY = "time";
  const WALL_TIME_KEY = "wall-time";
  const EXTRA_TIME_KEY = "extra-time";
  const STACK_SIZE_KEY = "stack-size";
  const MEMORY_KEY = "memory";
  const PARALLEL_KEY = "parallel";
  const DISK_SIZE_KEY = "disk-size";
  const DISK_FILES_KEY = "disk-files";
  const ENVIRON_KEY = "environ-variable";
  const CHDIR_KEY = "chdir";
  const BOUND_DIRECTORIES_KEY = "bound-directories";

  /** @var string ID of the hardware group */
  protected $id;

  /** @var float Time limit */
  protected $time = 0;

  /** @var float */
  protected $wallTime = 0;

  /** @var float */
  protected $extraTime = 0;

  /** @var int */
  protected $stackSize = 0;

  /** @var int Memory limit */
  protected $memory = 0;

  /** @var int */
  protected $parallel = 0;

  /** @var int */
  protected $diskSize = 0;

  /** @var int */
  protected $diskFiles = 0;

  /** @var string[] */
  protected $environVariables = [];

  /** @var string */
  protected $chdir = NULL;

  /** @var BoundDirectoryConfig[] */
  protected $boundDirectories = [];

  /** @var array Raw data */
  protected $data;

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
   * Hardware group identification
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Returns the time limit in milliseconds
   * @return int Number of milliseconds
   */
  public function getTimeLimit(): float {
    return $this->time;
  }

  public function getWallTime(): float {
    return $this->wallTime;
  }

  public function getExtraTime(): float {
    return $this->extraTime;
  }

  public function getStackSize(): int {
    return $this->stackSize;
  }

  /**
   * Returns the memory limit in bytes
   * @return int Number of bytes
   */
  public function getMemoryLimit(): int {
    return $this->memory;
  }

  public function getParallel(): int {
    return $this->parallel;
  }

  public function getDiskSize(): int {
    return $this->diskSize;
  }

  public function getDiskFiles(): int {
    return $this->diskFiles;
  }

  public function getEnvironVariables(): array {
    return $this->environVariables;
  }

  /**
   *
   * @return string|NULL
   */
  public function getChdir() {
    return $this->chdir;
  }

  public function getBoundDirectories(): array {
    return $this->boundDirectories;
  }

  public function toArray() {
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

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
