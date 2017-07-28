<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Model\Repository\Pipelines;


/**
 * Internal exercise configuration compilation service. Which handles merging
 * pipelines in each test, in the process arrays of boxes is created indexed by
 * tests identifications.
 */
class PipelinesMerger {

  /**
   * @var Pipelines
   */
  private $pipelines;

  /**
   * PipelinesMerger constructor.
   * @param Pipelines $pipelines
   */
  public function __construct(Pipelines $pipelines) {
    $this->pipelines = $pipelines;
  }


  /**
   * For each test merge its pipelines and create array of boxes
   * @param ExerciseConfig $exerciseConfig
   * @return array
   */
  public function merge(ExerciseConfig $exerciseConfig): array {
    return array();
  }

}
