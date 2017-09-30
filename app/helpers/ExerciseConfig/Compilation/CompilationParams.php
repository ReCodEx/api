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
  private $files = [];

  /**
   * Flag determining if compilation should include debug execution information.
   * @var bool
   */
  private $debug = false;


  /**
   * Get files submitted by user.
   * @return string[]
   */
  public function getFiles(): array {
    return $this->files;
  }

  /**
   * True if execution should include debug information and output files to
   * results.
   * @return bool
   */
  public function isDebug(): bool {
    return $this->debug;
  }

  /**
   * Factory.
   * @param array $files
   * @param bool $debug
   * @return CompilationParams
   */
  public static function create(array $files = [], bool $debug = false): CompilationParams {
    $params = new CompilationParams();
    $params->files = $files;
    $params->debug = $debug;
    return $params;
  }

}
