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
    return $node->getTestId() . self::$ID_DELIM . $node->getPipelineId() .
      self::$ID_DELIM . $node->getBox()->getName() . self::$ID_DELIM . $postfix;
  }

  /**
   * Set limits for all given hwgroups in given task.
   * @param Node $node
   * @param Task $task
   * @param ExerciseLimits[] $exerciseLimits indexed by hwgroup
   */
  private function setLimits(Node $node, Task $task, array $exerciseLimits) {
    if (!$task->getSandboxConfig()) {
      return;
    }

    $pipeline = $node->getPipelineId();
    $test = $node->getTestId();
    $box = $node->getBox()->getName();

    foreach ($exerciseLimits as $hwGroup => $hwGroupLimits) {
      $limits = $hwGroupLimits->getLimits($test, $pipeline, $box);
      if (!$limits) {
        continue;
      }

      $jobLimits = $limits->compile($hwGroup);
      $task->getSandboxConfig()->setLimits($jobLimits);
    }
  }

  /**
   * Perform DFS on the given tree and compile all appropriate boxes.
   * @param JobConfig $jobConfig
   * @param RootedTree $rootedTree
   * @param array $limits
   */
  private function processTree(JobConfig $jobConfig,
      RootedTree $rootedTree, array $limits) {
    // stack for DFS, better stay in order by reversing original root nodes
    $stack = array_reverse($rootedTree->getRootNodes());
    $order = 1;

    // main processing loop
    while (!empty($stack)) {
      $current = array_pop($stack);
      // compile box into set of tasks
      $tasks = $current->getBox()->compile();

      // set additional attributes to the tasks
      foreach ($tasks as $task) {
        // create and set task identification
        $taskId = $this->createTaskIdentification($current, $order);
        $current->addTaskId($taskId);
        $task->setId($taskId);

        // set global order/priority
        $task->setPriority($order);
        // construct and set dependencies
        $dependencies = array();
        foreach ($current->getParents() as $parent) {
          $dependencies = array_merge($dependencies, $parent->getTaskIds());
        }
        $task->setDependencies($dependencies);
        // set identification of test, if any
        if (!empty($current->getTestId())) {
          $task->setTestId($current->getTestId());
        }

        // if the task is external then set limits to it
        $this->setLimits($current, $task, $limits);

        // do not forget to add tasks into job configuration
        $jobConfig->addTask($task);

        // update helper vars
        $order++;
      }

      // add children of current node into stack
      foreach (array_reverse($current->getChildren()) as $child) {
        $stack[] = $child;
      }
    }
  }

  /**
   * Go through given array find boxes and compile them into JobConfig.
   * @param RootedTree $rootedTree
   * @param ExerciseLimits[] $limits indexed by hwgroup
   * @return JobConfig
   */
  public function compile(RootedTree $rootedTree, array $limits): JobConfig {
    $jobConfig = new JobConfig();

    // add hwgroups identifications into job configuration
    $jobConfig->getSubmissionHeader()->setHardwareGroups(array_keys($limits));
    // perform DFS
    $this->processTree($jobConfig, $rootedTree, $limits);

    return $jobConfig;
  }

}
