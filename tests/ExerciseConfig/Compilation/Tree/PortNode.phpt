<?php

include '../../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\VariablesTable;
use Tester\Assert;


class TestPortNode extends Tester\TestCase
{
  public function testVariablesTablesOperations() {
    $node = new PortNode(new CustomBox());

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

  public function testConstruction() {
    $box = new CustomBox();
    $pipelineId = "pipeline identification";
    $testId = "test identification";
    $node = new PortNode($box, $pipelineId, $testId);

    Assert::same($box, $node->getBox());
    Assert::equal($pipelineId, $node->getPipelineId());
    Assert::equal($testId, $node->getTestId());
  }

  public function testParents() {
    $node = new PortNode(new CustomBox());
    $parent = new PortNode(new CustomBox());
    $another = new PortNode(new CustomBox());
    Assert::count(0, $node->getParents());

    $node->addParent("parent", $parent);
    Assert::count(1, $node->getParents());
    Assert::true(array_key_exists("parent", $node->getParents()));
    Assert::same($parent, $node->getParents()["parent"]);

    $node->addParent("another parent", $another);
    Assert::count(2, $node->getParents());

    Assert::equal("parent", $node->findParentPort($parent));
    Assert::equal("another parent", $node->findParentPort($another));

    $node->removeParent($parent);
    Assert::count(1, $node->getParents());

    $node->clearParents();
    Assert::count(0, $node->getParents());
  }

  public function testChildren() {
    $node = new PortNode(new CustomBox());
    $child = new PortNode(new CustomBox());
    $another = new PortNode(new CustomBox());
    Assert::count(0, $node->getChildren());
    Assert::count(0, $node->getChildrenByPort());

    $node->addChild("child", $child);
    Assert::count(1, $node->getChildren());
    Assert::count(1, $node->getChildrenByPort());
    Assert::true(array_key_exists("child", $node->getChildrenByPort()));
    Assert::count(1, $node->getChildrenByPort()["child"]);
    Assert::same($child, current($node->getChildrenByPort()["child"]));

    $node->addChild("another child", $another);
    Assert::count(2, $node->getChildren());
    Assert::count(2, $node->getChildrenByPort());

    Assert::equal("child", $node->findChildPort($child));
    Assert::equal("another child", $node->findChildPort($another));

    $node->removeChild($child);
    Assert::count(1, $node->getChildren());
    Assert::count(2, $node->getChildrenByPort());
    Assert::count(0, $node->getChildrenByPort()["child"]);

    $node->clearChildren();
    Assert::count(0, $node->getChildren());
    Assert::count(0, $node->getChildrenByPort());
  }

  public function testFlags() {
    $node = new PortNode(new CustomBox());

    // inTree
    Assert::false($node->isInTree());
    $node->setInTree(true);
    Assert::true($node->isInTree());

    // visited
    Assert::false($node->isVisited());
    $node->setVisited(true);
    Assert::true($node->isVisited());

    // finished
    Assert::false($node->isFinished());
    $node->setFinished(true);
    Assert::true($node->isFinished());
  }

}

# Testing methods run
$testCase = new TestPortNode();
$testCase->run();
