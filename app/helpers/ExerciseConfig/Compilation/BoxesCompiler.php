<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Tasks\Task;

/**
 * Internal exercise configuration compilation service. Which is supposed to
 * compile boxes which comes in multidimensional array representing execution
 * order.
 */
class BoxesCompiler
{

    public static $ORDER_STARTING_POINT = 0;
    public static $ID_DELIM = ".";


    /**
     * Helper function which will create identification of task.
     * @param Node $node
     * @param string $postfix
     * @return string
     */
    private function createTaskIdentification(Node $node, string $postfix): string
    {
        $directoryName = $node->getBox()->getDirectory();
        return $directoryName . self::$ID_DELIM . $node->getPipelineId() .
            self::$ID_DELIM . $node->getBox()->getName() . self::$ID_DELIM . $postfix;
    }

    /**
     * Set limits for all given hwgroups in given task.
     * @param Node $node
     * @param Task $task
     * @param ExerciseLimits[] $exerciseLimits indexed by hwgroup
     */
    private function setLimits(Node $node, Task $task, array $exerciseLimits)
    {
        if (
            !$task->getSandboxConfig() || !$node->getTestId() ||
            $task->getType() !== TaskType::$EXECUTION
        ) {
            return;
        }

        $test = $node->getTestId();
        foreach ($exerciseLimits as $hwGroup => $hwGroupLimits) {
            $limits = $hwGroupLimits->getLimits($test);
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
     * @param CompilationContext $context
     * @param CompilationParams $params
     */
    private function processTree(
        JobConfig $jobConfig,
        RootedTree $rootedTree,
        CompilationContext $context,
        CompilationParams $params
    ) {
        // stack for DFS, better stay in order by reversing original root nodes
        $stack = array_reverse($rootedTree->getRootNodes());
        $order = self::$ORDER_STARTING_POINT;

        // main processing loop
        while (!empty($stack)) {
            $current = array_pop($stack);
            /** @var Node $current */
            $currentTestId = $current->getTestId();
            $currentTestName = null;
            if (!empty($currentTestId)) {
                $currentTestName = $context->getTestsNames()[$currentTestId];
            }

            // compile box into set of tasks
            $tasks = $current->getBox() ? $current->getBox()->compile($params) : [];

            // construct dependencies
            $dependencies = [];
            foreach ($current->getDependencies() as $dependency) {
                $dependencies = array_merge($dependencies, $dependency->getTaskIds());
            }
            $dependencies = array_unique($dependencies);

            // set additional attributes to the tasks
            $lastTaskId = null; // so we can properly link box tasks with dependencies
            foreach ($tasks as $task) {
                // create and set task identification
                $taskId = $this->createTaskIdentification($current, $order);
                $current->addTaskId($taskId);
                $task->setId($taskId);

                // construct and set dependencies
                $currentDependencies = $lastTaskId === null ? $dependencies
                    : array_merge($dependencies, [ $lastTaskId ]);
                $task->setDependencies($currentDependencies);

                // identification of test is present in node
                if (!empty($currentTestName)) {
                    // set identification of test to task
                    $task->setTestId($currentTestName);
                }

                // change evaluation directory to the one which belongs to test
                $sandbox = $task->getSandboxConfig();
                if ($current->getBox() && $sandbox) {
                    $sandbox->setWorkingDirectory($current->getBox()->getDirectory());
                }

                // if the task is external then set limits to it
                $this->setLimits($current, $task, $context->getLimits());

                // do not forget to add tasks into job configuration
                $jobConfig->addTask($task);

                // update helper vars
                $order++;
                $lastTaskId = $taskId;
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
     * @param CompilationContext $context
     * @param CompilationParams $params
     * @return JobConfig
     */
    public function compile(RootedTree $rootedTree, CompilationContext $context, CompilationParams $params): JobConfig
    {
        $jobConfig = new JobConfig();

        // loggin of submission is turned on by default
        $jobConfig->getSubmissionHeader()->setLog(true);
        // add hwgroups identifications into job configuration
        $jobConfig->getSubmissionHeader()->setHardwareGroups(array_keys($context->getLimits()));
        // perform DFS
        $this->processTree($jobConfig, $rootedTree, $context, $params);

        return $jobConfig;
    }
}
