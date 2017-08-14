<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\JobConfig\JobConfig;


/**
 * Internal exercise configuration compilation service. Which is supposed to
 * compile boxes which comes in multidimensional array representing execution
 * order.
 */
class BoxesCompiler {

  public static $ID_DELIM = ".";


  /**
   * Helper function which will create identification of task.
   * @param Node $node
   * @param string $postfix
   * @return string
   */
  private function createTaskIdentification(Node $node, string $postfix): string {
    return $node->getPipelineId() . self::$ID_DELIM . $node->getTestId() . self::$ID_DELIM . $postfix;
  }

  /**
   * Go through given array find boxes and compile them into JobConfig.
   * @param RootedTree $executionPipeline
   * @return JobConfig
   */
  public function compile(RootedTree $executionPipeline): JobConfig {
    $jobConfig = new JobConfig();

    // stack for DFS, better stay in order by reversing original root nodes
    $stack = array_reverse($executionPipeline->getRootNodes());
    $order = 1;

    // main processing loop
    while (!empty($stack)) {
      $localOrder = 1;
      $current = array_pop($stack);

      // compile box into set of tasks
      $tasks = $current->getBox()->compile();

      // set additional attributes to the tasks
      foreach ($tasks as $task) {
        $taskId = $this->createTaskIdentification($current, $localOrder);
        $current->addTaskId($taskId);

        // set global order/priority
        $task->setPriority($order);
        // construct and set dependencies
        $dependencies = array();
        foreach ($current->getParents() as $parent) {
          array_merge($dependencies, $parent->getTaskIds());
        }
        $task->setDependencies($dependencies);
        // set identification of test, if any
        if (!empty($current->getTestId())) {
          $task->setTestId($current->getTestId());
        }

        // update helper vars
        $localOrder++;
      }

      // update helper vars
      $order++;
    }

    return $jobConfig;
  }

}
