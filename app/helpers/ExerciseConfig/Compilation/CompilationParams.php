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
   * Variables submitted by user.
   * @var array
   */
  private $variables = [];

  /**
   * Flag determining if compilation should include debug execution information.
   * @var bool
   */
  private $debug = false;

  /**
   * Current test identification used during compilation.
   * @var string
   */
  private $currentTestName = null;


  /**
   * Get files submitted by user.
   * @return string[]
   */
  public function getFiles(): array {
    return $this->files;
  }

  /**
   * Get variables submitted by user.
   * @return array
   */
  public function getVariables(): array {
    return $this->variables;
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
   * Get current test identification which might be set and used during
   * compilation.
   * @return null|string
   */
  public function getCurrentTestName(): ?string {
    return $this->currentTestName;
  }

  /**
   * Set current test identification for the next compilation period.
   * @param null|string $testId
   */
  public function setCurrentTestName(?string $testId) {
    $this->currentTestName = $testId;
  }

  /**
   * Factory.
   * @param array $files
   * @param bool $debug
   * @param array $variables
   * @return CompilationParams
   */
  public static function create(array $files = [], bool $debug = false, array $variables = []): CompilationParams {
    $params = new CompilationParams();
    $params->files = $files;
    $params->variables = $variables;
    $params->debug = $debug;
    return $params;
  }

}
