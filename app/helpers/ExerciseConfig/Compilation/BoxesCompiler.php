<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Tasks\Task;


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
   * Set limits for all given hwgroups in given task.
   * @param Node $node
   * @param Task $task
   * @param ExerciseLimits[] $limits indexed by hwgroup
   */
  private function setLimits(Node $node, Task $task, array $limits) {
    if (!$task->getSandboxConfig()) {
      return;
    }

    $pipeline = $node->getPipelineId();
    $test = $node->getTestId();
    $box = $node->getBox()->getName();

    foreach ($limits as $hwGroup => $groupLimits) {
      $jobLimits = $groupLimits->getLimits($test, $pipeline, $box)->compile($hwGroup);
      $task->getSandboxConfig()->setLimits($jobLimits);
    }
  }

  /**
   * Go through given array find boxes and compile them into JobConfig.
   * @param RootedTree $executionPipeline
   * @param ExerciseLimits[] $limits indexed by hwgroup
   * @return JobConfig
   */
  public function compile(RootedTree $executionPipeline, array $limits): JobConfig {
    $jobConfig = new JobConfig();

    // stack for DFS, better stay in order by reversing original root nodes
    $stack = array_reverse($executionPipeline->getRootNodes());
    $order = 1;

    // main processing loop
    while (!empty($stack)) {
      $current = array_pop($stack);

      // compile box into set of tasks
      $tasks = $current->getBox()->compile();

      // set additional attributes to the tasks
      foreach ($tasks as $task) {
        $taskId = $this->createTaskIdentification($current, $order);
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

        // if the task is external then set limits to it
        $this->setLimits($current, $task, $limits);

        // update helper vars
        $order++;
      }
    }

    return $jobConfig;
  }

}
