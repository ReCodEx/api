<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\TestConfig;
use App\Helpers\JobConfig\JobId;
use App\Helpers\Yaml;

/**
 * Evaluation results of whole job
 */
class EvaluationResults
{
    public const JOB_ID_KEY = "job-id";
    public const HARDWARE_GROUP_KEY = "hw-group";
    public const RESULTS_KEY = "results";
    public const TASK_ID_KEY = "task-id";

    /** @var string Hardware group identifier of the worker that processed the job */
    private $hardwareGroup;

    /** @var array Raw data from the results */
    private $rawResults;

    /** @var array Assoc array of the tasks */
    private $tasks = [];

    /** @var JobConfig The configuration of the job */
    private $config;

    /** @var bool */
    private $initOK = true;

    /**
     * Constructor
     * @param array $rawResults Raw results of evaluation from backend (just parsed YAML)
     * @param JobConfig $config Configuration of this job
     * @throws ResultsLoadingException
     */
    public function __construct(array $rawResults, JobConfig $config)
    {
        if (!isset($rawResults[self::JOB_ID_KEY])) {
            throw new ResultsLoadingException("Job ID is not set in the result.");
        }

        $jobId = new JobId($rawResults[self::JOB_ID_KEY]);
        if ((string)$jobId !== $config->getJobId()) {
            throw new ResultsLoadingException("Job ID of the configuration and the result do not match.");
        }

        if (!isset($rawResults[self::HARDWARE_GROUP_KEY])) {
            throw new ResultsLoadingException("Hardware group ID is not set in the result.");
        }

        $this->hardwareGroup = $rawResults[self::HARDWARE_GROUP_KEY];

        if (!isset($rawResults[self::RESULTS_KEY])) {
            throw new ResultsLoadingException("Results are missing required field 'results'.");
        }

        if (!is_array($rawResults[self::RESULTS_KEY])) {
            throw new ResultsLoadingException("Results field of the results must be an array.");
        }

        $this->config = $config;
        $this->rawResults = $rawResults;

        // store all the reported results
        $this->tasks = [];
        foreach ($rawResults[self::RESULTS_KEY] as $task) {
            if (!isset($task[self::TASK_ID_KEY])) {
                throw new ResultsLoadingException("One of the task's result is missing 'task-id'");
            }

            $taskId = $task[self::TASK_ID_KEY];
            $this->tasks[$taskId] = new TaskResult($task);
        }

        // test if all the tasks in the config file have corresponding results
        // and also check if all the initiation tasks were successful
        // - missing task results are replaced with skipped task results
        foreach ($this->config->getTasks() as $taskCfg) {
            $id = $taskCfg->getId();
            if (!isset($this->tasks[$id])) {
                $this->tasks[$id] = new SkippedTaskResult($id);
            }

            if ($taskCfg->isInitiationTask() && !$this->tasks[$id]->isOK()) {
                $this->initOK = false;
            }
        }
    }

    /**
     * Initialization was OK
     * @return boolean The result
     */
    public function initOK()
    {
        return $this->initOK;
    }

    /**
     * Get the hardware group identifier of the worker responsible for the evaluation
     */
    public function getHardwareGroupId(): string
    {
        return $this->hardwareGroup;
    }

    /**
     * Get outputs for tasks marked with initiation type.
     * @return string Concatenated outputs for all initiation tasks
     */
    public function getInitiationOutputs()
    {
        $initTaskCfgs = array_filter(
            $this->config->getTasks(),
            function ($taskCfg) {
                return $taskCfg->isInitiationTask();
            }
        );

        $outputs = [];
        foreach ($initTaskCfgs as $taskCfg) {
            $taskOutput = $this->tasks[$taskCfg->getId()]->getOutput();
            $outputs[] = $taskOutput;
        }

        return implode("\n", $outputs);
    }

    /**
     * Get results for all logical tests, one result per test
     * @return TestResult[] Results of all test inside job
     * @internal param string $hardwareGroupId Hardware group
     */
    public function getTestsResults()
    {
        return array_map(
            function ($test) {
                return $this->getTestResult($test);
            },
            $this->config->getTests()
        );
    }

    /**
     * Get (aggregate) result for one test
     * @param TestConfig $test Configuration of the test
     * @return TestResult Results for specified test
     */
    public function getTestResult(TestConfig $test)
    {
        $execTasks = $this->getExecutionTasksResult($test);
        $eval = $this->getEvaluationTaskResult($test);
        return new TestResult($test, $execTasks, $eval, $this->hardwareGroup);
    }

    /**
     * Get aggregated results for all execution tasks in test
     * @param TestConfig $test Configuration of the examined test
     * @return ExecutionTaskResult[] Results for tasks in specified test
     */
    private function getExecutionTasksResult(TestConfig $test)
    {
        $executionTasks = $test->getExecutionTasks();
        $resultsPerTask = [];
        foreach ($executionTasks as $task) {
            $resultsPerTask[] = $this->tasks[$task->getId()]->getAsExecutionTaskResult();
        }
        return $resultsPerTask;
    }

    /**
     * Get simple results for single evaluation tasks
     * @param TestConfig $test Configuration of the examined test
     * @return EvaluationTaskResult Result for evaluation of specified test
     */
    private function getEvaluationTaskResult(TestConfig $test)
    {
        $id = $test->getEvaluationTask()->getId();
        return $this->tasks[$id]->getAsEvaluationTaskResult();
    }

    /**
     * Save raw results to string
     * @return string Serialized data in YAML format
     */
    public function __toString(): string
    {
        return Yaml::dump($this->rawResults);
    }
}
