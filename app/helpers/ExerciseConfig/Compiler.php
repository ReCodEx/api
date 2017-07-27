<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\ExerciseConfig\Compilation\ExerciseConfigCompiler;
use App\Helpers\JobConfig\JobConfig;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\RuntimeEnvironment;

/**
 * Compiler used for generating JobConfig structure from ExerciseConfig,
 * meaning, high-level format is compiled into low-level format which can be
 * executed on backend workers.
 */
class Compiler {

  /**
   * @var ExerciseConfigCompiler
   */
  private $exerciseConfigCompiler;


  /**
   * Compiler constructor.
   * @param ExerciseConfigCompiler $exerciseConfigCompiler
   */
  public function __construct(ExerciseConfigCompiler $exerciseConfigCompiler) {
    $this->exerciseConfigCompiler = $exerciseConfigCompiler;
  }

  /**
   * Generate job configuration from given exercise configuration.
   * @param ExerciseConfig $config
   * @param RuntimeEnvironment $runtimeEnvironment
   * @return JobConfig
   */
  public function compile(): JobConfig {
    return $this->exerciseConfigCompiler->compile();
  }
}
