<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\TestConfig;
use App\Helpers\JobConfig\Tasks\Task;
use App\Helpers\JobConfig\SandboxConfig;
use App\Exceptions\JobConfigLoadingException;

class TestTestResult extends Tester\TestCase
{
    /** @var Task */
    private $evaluationTask;

    /** @var Task */
    private $executionTask;

    protected function setUp()
    {
        $this->evaluationTask = (new Task())->setId("X")->setTestId("A")->setType("evaluation");
        $this->executionTask = (new Task())->setId("Y")->setTestId("A")->setType("execution")
            ->setSandboxConfig((new SandboxConfig())->setName("sandboxName"));
    }

    public function testMissingExecutionOrEvaluationTask()
    {
        Assert::exception(
            function () {
                new TestConfig(
                    "some ID",
                    [
                        (new Task())->setId("A"),
                        (new Task())->setId("B"),
                        (new Task())->setId("C"),
                        (new Task())->setId("D")
                    ]
                );
            },
            JobConfigLoadingException::class
        );

        Assert::exception(
            function () {
                new TestConfig(
                    "some ID",
                    [
                        (new Task())->setId("A"),
                        $this->executionTask,
                        (new Task())->setId("C"),
                        (new Task())->setId("D")
                    ]
                );
            },
            JobConfigLoadingException::class
        );

        Assert::exception(
            function () {
                new TestConfig(
                    "some ID",
                    [
                        (new Task())->setId("A"),
                        (new Task())->setId("B"),
                        $this->evaluationTask,
                        (new Task())->setId("D")
                    ]
                );
            },
            JobConfigLoadingException::class
        );
    }

    public function testBothExecutionOrEvaluationTasksPresent()
    {
        $cfg = new TestConfig(
            "some ID",
            [
                (new Task())->setId("A"),
                $this->executionTask,
                (new Task())->setId("C"),
                $this->evaluationTask,
                (new Task())->setId("D")
            ]
        );

        Assert::equal("some ID", $cfg->getId());
    }

    public function testExecutionOrEvaluationTasksAvailability()
    {
        $cfg = new TestConfig(
            "some ID",
            [
                (new Task())->setId("A"),
                $this->executionTask,
                (new Task())->setId("C"),
                $this->evaluationTask,
                (new Task())->setId("D")
            ]
        );

        Assert::true($cfg->getExecutionTasks()[0]->isExecutionTask());
        Assert::equal("Y", $cfg->getExecutionTasks()[0]->getId());
        Assert::equal("X", $cfg->getEvaluationTask()->getId());
    }
}

# Testing methods run
$testCase = new TestTestResult();
$testCase->run();
