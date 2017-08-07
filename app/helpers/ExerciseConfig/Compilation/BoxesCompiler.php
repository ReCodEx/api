<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Tree;
use App\Helpers\JobConfig\JobConfig;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * compile boxes which comes in multidimensional array representing execution
 * order.
 */
class BoxesCompiler {

  /**
   * Go through given array find boxes and compile them into JobConfig.
   * @param Tree $executionPipeline
   * @return JobConfig
   */
  public function compile(Tree $executionPipeline): JobConfig {
    return new JobConfig();
  }

}
