<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\JobConfig\JobConfig;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * compile boxes which comes in multidimensional array representing execution
 * order.
 */
class BoxesCompiler {

  /**
   * Go through given array find boxes and compile them into JobConfig.
   * @param RootedTree $executionPipeline
   * @return JobConfig
   */
  public function compile(RootedTree $executionPipeline): JobConfig {
    $jobConfig = new JobConfig();

    // stack for DFS, better stay in order by reversing original root nodes
    $stack = array_reverse($executionPipeline->getRootNodes());

    // main processing loop
    while (!empty($stack)) {
      $current = array_pop($stack);
      // @todo: do magic
    }

    return $jobConfig;
  }

}
