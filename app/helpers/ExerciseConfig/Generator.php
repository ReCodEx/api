<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\JobConfig\JobConfig;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\RuntimeEnvironment;

/**
 * @todo
 */
class Generator {

  /**
   * @todo: generate actual job config
   *
   * @param ExerciseConfig $config
   * @return JobConfig
   */
  public function generateJobConfig(ExerciseConfig $config, RuntimeEnvironment $runtimeEnvironment): JobConfig {
    return new JobConfig;
  }
}
