<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\JobConfig\JobConfig;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise configuration compilation service.
 */
class ExerciseConfigCompiler {

  /**
   * @var Pipelines
   */
  private $pipelines;

  /**
   * ExerciseConfigValidator constructor.
   * @param Pipelines $pipelines
   */
  public function __construct(Pipelines $pipelines) {
    $this->pipelines = $pipelines;
  }


  /**
   * Compile ExerciseConfig to JobConfig
   * @return JobConfig
   */
  public function compile(): JobConfig {
    return new JobConfig();
  }

}
