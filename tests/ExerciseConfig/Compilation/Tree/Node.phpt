<?php

include '../../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use Tester\Assert;


class TestNode extends Tester\TestCase
{

  public function testConstruction() {
    $box = new CustomBox();
    $pipeline = "pipeline identification";
    $test = "test identification";
    $portNode = new PortNode($box, $pipeline, $test);
    $node = new Node($portNode);

    Assert::same($box, $node->getBox());
    Assert::equal($pipeline, $node->getPipelineId());
    Assert::equal($test, $node->getTestId());
  }

  public function testPipelineAndTest() {
    $portNode = new PortNode(new CustomBox());
    $node = new Node($portNode);

    Assert::null($node->getPipelineId());
    Assert::null($node->getTestId());

    $pipeline = "pipeline";
    $test = "test";
    $node->setPipelineId($pipeline);
    $node->setTestId($test);

    Assert::equal($pipeline, $node->getPipelineId());
    Assert::equal($test, $node->getTestId());
  }

  public function testTasks() {
    $node = new Node(new PortNode(new CustomBox()));
    Assert::count(0, $node->getTaskIds());

    $task1 = "task one";
    $node->addTaskId($task1);
    Assert::count(1, $node->getTaskIds());
    Assert::contains($task1, $node->getTaskIds());

    $task2 = "task two";
    $node->addTaskId($task2);
    Assert::count(2, $node->getTaskIds());
    Assert::contains($task2, $node->getTaskIds());
  }

  public function testParents() {
    $node = new Node(new PortNode(new CustomBox()));
    $parent1 = new Node(new PortNode(new CustomBox()));
    $parent2 = new Node(new PortNode(new CustomBox()));
    Assert::count(0, $node->getParents());

    $node->addParent($parent1);
    Assert::count(1, $node->getParents());
    Assert::contains($parent1, $node->getParents());

    $node->addParent($parent2);
    Assert::count(2, $node->getParents());
    Assert::contains($parent2, $node->getParents());

    $node->removeParent($parent1);
    Assert::count(1, $node->getParents());
    Assert::contains($parent2, $node->getParents());
  }

  public function testChildren() {
    $node = new Node(new PortNode(new CustomBox()));
    $child1 = new Node(new PortNode(new CustomBox()));
    $child2 = new Node(new PortNode(new CustomBox()));
    Assert::count(0, $node->getChildren());

    $node->addChild($child1);
    Assert::count(1, $node->getChildren());
    Assert::contains($child1, $node->getChildren());

    $node->addChild($child2);
    Assert::count(2, $node->getChildren());
    Assert::contains($child2, $node->getChildren());

    $node->removeChild($child1);
    Assert::count(1, $node->getChildren());
    Assert::contains($child2, $node->getChildren());
  }

}

# Testing methods run
$testCase = new TestNode();
$testCase->run();
