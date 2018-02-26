<?php

namespace App\Helpers\ExerciseConfig\EntityMetadata;

use Symfony\Component\Yaml\Yaml;


/**
 * Contains meta information about exercise configuration which applies to
 * specific hardware group.
 */
class HwGroupMeta {

  private static $MEMORY_KEY = "memory";
  private static $CPU_TIME_PER_TEST_KEY = "cpuTimePerTest";
  private static $CPU_TIME_PER_EXERCISE_KEY = "cpuTimePerExercise";
  private static $WALL_TIME_PER_TEST_KEY = "wallTimePerTest";
  private static $WALL_TIME_PER_EXERCISE_KEY = "wallTimePerExercise";


  /**
   * Maximal memory limit for hwgroup in kilobytes.
   * @var int
   */
  private $memory = 0;

  /**
   * Maximal cpu-time for hwgroup per test in seconds.
   * @var float
   */
  private $cpuTimePerTest = 0;

  /**
   * Maximal cpu-time for hwgroup per exercise in seconds.
   * @var float
   */
  private $cpuTimePerExercise = 0;

  /**
   * Maximal wall-time for hwgroup per test in seconds.
   * @var float
   */
  private $wallTimePerTest = 0;

  /**
   * Maximal wall-time for hwgroup per exercise in seconds.
   * @var float
   */
  private $wallTimePerExercise = 0;


  /**
   * HwGroupMeta constructor.
   * @param string $data
   */
  public function __construct(string $data) {
    $parsed = Yaml::parse($data);

    if (array_key_exists(self::$MEMORY_KEY, $parsed)) {
      $this->memory = $parsed[self::$MEMORY_KEY];
    }

    if (array_key_exists(self::$CPU_TIME_PER_TEST_KEY, $parsed)) {
      $this->cpuTimePerTest = $parsed[self::$CPU_TIME_PER_TEST_KEY];
    }

    if (array_key_exists(self::$CPU_TIME_PER_EXERCISE_KEY, $parsed)) {
      $this->cpuTimePerExercise = $parsed[self::$CPU_TIME_PER_EXERCISE_KEY];
    }

    if (array_key_exists(self::$WALL_TIME_PER_TEST_KEY, $parsed)) {
      $this->wallTimePerTest = $parsed[self::$WALL_TIME_PER_TEST_KEY];
    }

    if (array_key_exists(self::$WALL_TIME_PER_EXERCISE_KEY, $parsed)) {
      $this->wallTimePerExercise = $parsed[self::$WALL_TIME_PER_EXERCISE_KEY];
    }
  }


  /**
   * Maximal memory limit for hwgroup in kilobytes.
   * @return int
   */
  public function getMemory(): int {
    return $this->memory;
  }

  /**
   * Maximal cpu-time for hwgroup per test in seconds.
   * @return float
   */
  public function getCpuTimePerTest(): float {
    return $this->cpuTimePerTest;
  }

  /**
   * Maximal cpu-time for hwgroup per exercise in seconds.
   * @return float
   */
  public function getCpuTimePerExercise(): float {
    return $this->cpuTimePerExercise;
  }

  /**
   * Maximal wall-time for hwgroup per test in seconds.
   * @return float
   */
  public function getWallTimePerTest(): float {
    return $this->wallTimePerTest;
  }

  /**
   * Maximal wall-time for hwgroup per exercise in seconds.
   * @return float
   */
  public function getWallTimePerExercise(): float {
    return $this->wallTimePerExercise;
  }


  /**
   * Serialize hardware group meta information into array.
   * @return array
   */
  public function toArray(): array {
    return [
      self::$MEMORY_KEY => $this->memory,
      self::$CPU_TIME_PER_TEST_KEY => $this->cpuTimePerTest,
      self::$CPU_TIME_PER_EXERCISE_KEY => $this->cpuTimePerExercise,
      self::$WALL_TIME_PER_TEST_KEY => $this->wallTimePerTest,
      self::$WALL_TIME_PER_EXERCISE_KEY => $this->wallTimePerExercise
    ];
  }
}
