<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Variable;


/**
 * Static internal exercise configuration validation helper.
 */
class ValidationUtils {

  /**
   * Check if given variable is remote-file or files and if so, check if
   * filename in the variable value is present in given array of exercise
   * files.
   * @param Variable $variable
   * @param array $files indexed by filename, contains file hash
   * @param string $fileType files resource
   * @throws ExerciseConfigException
   */
  public static function checkRemoteFilePresence(Variable $variable, array $files, string $fileType) {
    if (!$variable->isRemoteFile() || $variable->isEmpty()) {
      return;
    }

    foreach ($variable->getValueAsArray() as $value) {
      if (!array_key_exists($value, $files)) {
        throw new ExerciseConfigException("Remote file '{$value}' not found in {$fileType} files.");
      }
    }
  }

}
