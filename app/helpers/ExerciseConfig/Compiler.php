<?php

namespace App\Helpers\ExerciseConfig;

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
   * Generate job configuration from given exercise configuration.
   * @param ExerciseConfig $config
   * @param RuntimeEnvironment $runtimeEnvironment
   * @return JobConfig
   */
  public function compileExerciseConfig(): JobConfig {
    return new JobConfig;
  }
}
