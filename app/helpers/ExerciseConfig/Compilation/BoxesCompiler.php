<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\JobConfig\JobConfig;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * compile boxes which comes in multidimensional array representing execution
 * order.
 */
class BoxesCompiler {

  /**
   * Go through given array find boxes and compile them into JobConfig.
   * @param array $executionPipeline
   * @return JobConfig
   */
  public function compile(array $executionPipeline): JobConfig {
    return new JobConfig();
  }

}
