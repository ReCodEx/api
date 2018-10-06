<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ParseException;
use App\Helpers\EntityMetadata\Solution\SolutionParams;


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
   * Solution parameters as submitted by user.
   * @var SolutionParams
   */
  private $solutionParams = null;

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
   * Get solution parameters submitted by user.
   * @return SolutionParams
   */
  public function getSolutionParams(): SolutionParams {
    return $this->solutionParams;
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
   * @param SolutionParams $solutionParams
   * @return CompilationParams
   * @throws ParseException
   */
  public static function create(array $files = [], bool $debug = false, SolutionParams $solutionParams = null): CompilationParams {
    $params = new CompilationParams();
    $params->files = $files;
    $params->solutionParams = $solutionParams ?: new SolutionParams();
    $params->debug = $debug;
    return $params;
  }

}
