<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesOptimizer;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use Tester\Assert;

/**
 * @testCase
 */
class TestBoxesOptimizer extends Tester\TestCase
{
  /** @var BoxesOptimizer */
  private $optimizer;

  public function __construct() {
    $this->optimizer = new BoxesOptimizer();
  }

  public function testSimpleTree() {
    $tests = [
      "A" => (new RootedTree())->addRootNode((new Node())->setBox(new CustomBox("node")))
    ];

    $tree = $this->optimizer->optimize($tests);
    Assert::count(1, $tree->getRootNodes());

    $actualNode = $tree->getRootNodes()[0];
    Assert::equal("node", $actualNode->getBox()->getName());
    Assert::count(0, $actualNode->getParents());
    Assert::count(0, $actualNode->getChildren());
  }

  public function testTwoSimpleTrees() {
    $tests = [
      "A" => (new RootedTree())->addRootNode((new Node())->setBox(new CustomBox("nodeA"))),
      "B" => (new RootedTree())->addRootNode((new Node())->setBox(new CustomBox("nodeB")))
    ];

    $tree = $this->optimizer->optimize($tests);
    Assert::count(1, $tree->getRootNodes());

    $actualNode = $tree->getRootNodes()[0];
    Assert::equal("nodeA", $actualNode->getBox()->getName());
    Assert::count(0, $actualNode->getParents());
    Assert::count(0, $actualNode->getChildren());
  }

  public function testTwoDistinctTrees() {
    $nodeA1 = (new Node())->setBox((new CustomBox("nodeA1"))->addOutputPort(new Port(PortMeta::create("", "string"))));
    $nodeA2 = (new Node())->setBox((new CustomBox("nodeA2"))->addInputPort(new Port(PortMeta::create("", "string"))));
    $nodeA1->addChild($nodeA2);
    $nodeA2->addParent($nodeA1);

    $nodeB1 = (new Node())->setBox((new CustomBox("nodeB1"))->addOutputPort(new Port(PortMeta::create("", "file"))));
    $nodeB2 = (new Node())->setBox((new CustomBox("nodeB2"))->addInputPort(new Port(PortMeta::create("", "file"))));
    $nodeB1->addChild($nodeB2);
    $nodeB2->addParent($nodeB1);

    $tests = [
      "A" => (new RootedTree())->addRootNode($nodeA1),
      "B" => (new RootedTree())->addRootNode($nodeB1)
    ];

    $tree = $this->optimizer->optimize($tests);
    Assert::count(2, $tree->getRootNodes());

    $actualNodeA = $tree->getRootNodes()[0];
    Assert::equal("nodeA1", $actualNodeA->getBox()->getName());
    Assert::count(0, $actualNodeA->getParents());
    Assert::count(1, $actualNodeA->getChildren());
    Assert::equal("nodeA2", $actualNodeA->getChildren()[0]->getBox()->getName());

    $actualNodeB = $tree->getRootNodes()[1];
    Assert::equal("nodeB1", $actualNodeB->getBox()->getName());
    Assert::count(0, $actualNodeB->getParents());
    Assert::count(1, $actualNodeB->getChildren());
    Assert::equal("nodeB2", $actualNodeB->getChildren()[0]->getBox()->getName());
  }

  public function testTwoPartlySameTrees() {
    $nodeA1 = (new Node())->setBox((new CustomBox("nodeA1"))->addOutputPort(new Port(PortMeta::create("", "string"))));
    $nodeA2 = (new Node())->setBox((new CustomBox("nodeA2"))->addInputPort(new Port(PortMeta::create("", "string"))));
    $nodeA1->addChild($nodeA2);
    $nodeA2->addParent($nodeA1);

    $nodeB1 = (new Node())->setBox((new CustomBox("nodeB1"))->addOutputPort(new Port(PortMeta::create("", "string"))));
    $nodeB2 = (new Node())->setBox((new CustomBox("nodeB2"))
      ->addInputPort(new Port(PortMeta::create("", "string")))->addInputPort(new Port(PortMeta::create("", "file"))));
    $nodeB1->addChild($nodeB2);
    $nodeB2->addParent($nodeB1);

    $tests = [
      "A" => (new RootedTree())->addRootNode($nodeA1),
      "B" => (new RootedTree())->addRootNode($nodeB1)
    ];

    $tree = $this->optimizer->optimize($tests);
    Assert::count(1, $tree->getRootNodes());

    $actualNode = $tree->getRootNodes()[0];
    Assert::equal("nodeA1", $actualNode->getBox()->getName());
    Assert::count(0, $actualNode->getParents());
    Assert::count(2, $actualNode->getChildren());

    $childA = $actualNode->getChildren()[0];
    Assert::equal("nodeA2", $childA->getBox()->getName());
    Assert::count(1, $childA->getParents());
    Assert::equal("nodeA1", $childA->getParents()[0]->getBox()->getName());
    Assert::count(0, $childA->getChildren());

    $childB = $actualNode->getChildren()[1];
    Assert::equal("nodeB2", $childB->getBox()->getName());
    Assert::count(1, $childB->getParents());
    Assert::equal("nodeA1", $childB->getParents()[0]->getBox()->getName());
    Assert::count(0, $childB->getChildren());
  }

  public function testThreeComplexTrees() {
    $treeA = new RootedTree();
    $treeB = new RootedTree();
    $treeC = new RootedTree();

    $tests = [
      "A" => $treeA,
      "B" => $treeB,
      "C" => $treeC
    ];

    $tree = $this->optimizer->optimize($tests);

    // @todo
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestBoxesOptimizer();
$testCase->run();
