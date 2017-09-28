<?php

namespace App\Helpers\ExerciseConfig\Compilation;


/**
 * Holder for various general compilation parameters.
 */
class CompilationParams {

  /**
   * Files submitted by user.
   * @var string[]
   */
  public $submittedFiles = [];

  /**
   * Flag determining if compilation should include debug execution information.
   * @var bool
   */
  public $debugSubmission = false;


  /**
   * Factory.
   * @param array $files
   * @param bool $debug
   * @return CompilationParams
   */
  public static function create(array $files = [], bool $debug = false): CompilationParams {
    $params = new CompilationParams();
    $params->submittedFiles = $files;
    $params->debugSubmission = $debug;
    return $params;
  }

}
