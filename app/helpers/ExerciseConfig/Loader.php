<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigLoadingException;

/**
 * Loader service which is able to load exercise configuration into internal
 * holders. Given data are checked against mandatory fields and in case of error
 * exception is thrown.
 */
class Loader {

  /**
   * Builds and checks limits from given structured data.
   * @param array $data
   * @param string $boxId Box identifier (name) for better error messages
   * @return Limits
   * @throws ExerciseConfigLoadingException
   */
  public function loadLimits($data, $boxId = ""): Limits {
    $limits = new Limits;

    if (!is_array($data)) {
      throw new ExerciseConfigLoadingException("Box '" . $boxId . "': limits are not array");
    }

    // *** LOAD OPTIONAL DATAS

    if (isset($data[Limits::WALL_TIME_KEY])) {
      $limits->setWallTime(floatval($data[Limits::WALL_TIME_KEY]));
      unset($data[Limits::WALL_TIME_KEY]);
    }

    if (isset($data[Limits::MEMORY_KEY])) {
      $limits->setMemoryLimit(intval($data[Limits::MEMORY_KEY]));
      unset($data[Limits::MEMORY_KEY]);
    }

    if (isset($data[Limits::PARALLEL_KEY])) {
      $limits->setParallel(intval($data[Limits::PARALLEL_KEY]));
      unset($data[Limits::PARALLEL_KEY]);
    }

    return $limits;
  }

  public function loadExerciseLimits($data) {
    $limits = new ExerciseLimits;

    if (!is_array($data)) {
      throw new ExerciseConfigLoadingException("Exercise limits are not array");
    }

    foreach ($data as $key => $values) {
      $limits->addLimits($key, $this->loadLimits($values, $key));
    }

    return $limits;
  }

}
