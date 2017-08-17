<?php

include '../../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use Tester\Assert;


class TestRootedTree extends Tester\TestCase
{

  public function testRootNodes() {
    $tree = new RootedTree();
    Assert::count(0, $tree->getRootNodes());

    $portNode1 = new PortNode(new CustomBox());
    $node1 = new Node($portNode1);
    $tree->addRootNode($node1);
    Assert::count(1, $tree->getRootNodes());
    Assert::same($node1, current($tree->getRootNodes()));

    $portNode2 = new PortNode(new CustomBox());
    $node2 = new Node($portNode2);
    $tree->addRootNode($node2);
    Assert::count(2, $tree->getRootNodes());
    Assert::same($node2, $tree->getRootNodes()[1]);
  }

}

# Testing methods run
$testCase = new TestRootedTree();
$testCase->run();
