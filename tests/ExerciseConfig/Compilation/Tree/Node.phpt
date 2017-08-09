<?php

include '../../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\VariablesTable;
use Tester\Assert;


class TestNode extends Tester\TestCase
{
  public function testVariablesTablesOperations() {
    $node = new Node();

    Assert::null($node->getEnvironmentConfigVariables());
    Assert::null($node->getExerciseConfigVariables());
    Assert::null($node->getPipelineVariables());

    $envVariables = new VariablesTable();
    $exeVariables = new VariablesTable();
    $pipeVariables = new VariablesTable();

    $node->setEnvironmentConfigVariables($envVariables);
    $node->setExerciseConfigVariables($exeVariables);
    $node->setPipelineVariables($pipeVariables);

    Assert::notEqual(null, $node->getEnvironmentConfigVariables());
    Assert::notEqual(null, $node->getExerciseConfigVariables());
    Assert::notEqual(null, $node->getPipelineVariables());

    Assert::equal(0, $node->getEnvironmentConfigVariables()->size());
    Assert::equal(0, $node->getExerciseConfigVariables()->size());
    Assert::equal(0, $node->getPipelineVariables()->size());
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestNode();
$testCase->run();
