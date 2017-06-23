<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\JobConfig\JobConfig;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\RuntimeEnvironment;

/**
 * @todo
 */
class Compiler {

  /**
   * Generate job configuration from given exercise configuration.
   * @param ExerciseConfig $config
   * @return JobConfig
   */
  public function compileExerciseConfig(ExerciseConfig $config, RuntimeEnvironment $runtimeEnvironment): JobConfig {
    return new JobConfig;
  }
}
