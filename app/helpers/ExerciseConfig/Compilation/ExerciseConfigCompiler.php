<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\VariablesTable;
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
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $variablesTable
   * @return JobConfig
   */
  public function compile(ExerciseConfig $exerciseConfig, VariablesTable $variablesTable): JobConfig {
    return new JobConfig();
  }

}
