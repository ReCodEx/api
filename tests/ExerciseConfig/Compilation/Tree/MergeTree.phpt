<?php

include '../../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\VariablesTable;
use Tester\Assert;


class TestMergeTree extends Tester\TestCase
{

    public function testInputNodes()
    {
        $tree = new MergeTree();
        Assert::count(0, $tree->getInputNodes());

        $node1 = new PortNode(new CustomBox());
        $tree->addInputNode($node1);
        Assert::count(1, $tree->getInputNodes());
        Assert::contains($node1, $tree->getInputNodes());

        $node2 = new PortNode(new CustomBox());
        $tree->addInputNode($node2);
        Assert::count(2, $tree->getInputNodes());
        Assert::contains($node2, $tree->getInputNodes());

        $node3 = new PortNode(new CustomBox());
        $node4 = new PortNode(new CustomBox());
        $tree->setInputNodes([$node3, $node4]);
        Assert::count(2, $tree->getInputNodes());
        Assert::contains($node3, $tree->getInputNodes());
        Assert::contains($node4, $tree->getInputNodes());
    }

    public function testOutputNodes()
    {
        $tree = new MergeTree();
        Assert::count(0, $tree->getOutputNodes());

        $node1 = new PortNode(new CustomBox());
        $tree->addOutputNode($node1);
        Assert::count(1, $tree->getOutputNodes());
        Assert::contains($node1, $tree->getOutputNodes());

        $node2 = new PortNode(new CustomBox());
        $tree->addOutputNode($node2);
        Assert::count(2, $tree->getOutputNodes());
        Assert::contains($node2, $tree->getOutputNodes());

        $node3 = new PortNode(new CustomBox());
        $node4 = new PortNode(new CustomBox());
        $tree->setOutputNodes([$node3, $node4]);
        Assert::count(2, $tree->getOutputNodes());
        Assert::contains($node3, $tree->getOutputNodes());
        Assert::contains($node4, $tree->getOutputNodes());
    }

    public function testOtherNodes()
    {
        $tree = new MergeTree();
        Assert::count(0, $tree->getOtherNodes());

        $node1 = new PortNode(new CustomBox());
        $tree->addOtherNode($node1);
        Assert::count(1, $tree->getOtherNodes());
        Assert::contains($node1, $tree->getOtherNodes());

        $node2 = new PortNode(new CustomBox());
        $tree->addOtherNode($node2);
        Assert::count(2, $tree->getOtherNodes());
        Assert::contains($node2, $tree->getOtherNodes());

        $node3 = new PortNode(new CustomBox());
        $node4 = new PortNode(new CustomBox());
        $tree->setOtherNodes([$node3, $node4]);
        Assert::count(2, $tree->getOtherNodes());
        Assert::contains($node3, $tree->getOtherNodes());
        Assert::contains($node4, $tree->getOtherNodes());
    }

    public function testAllNodes()
    {
        $tree = new MergeTree();
        Assert::count(0, $tree->getAllNodes());

        $node1 = new PortNode(new CustomBox());
        $node2 = new PortNode(new CustomBox());
        $node3 = new PortNode(new CustomBox());
        $tree->addInputNode($node1);
        $tree->addOutputNode($node2);
        $tree->addOtherNode($node3);

        Assert::count(3, $tree->getAllNodes());
        Assert::contains($node1, $tree->getAllNodes());
        Assert::contains($node2, $tree->getAllNodes());
        Assert::contains($node3, $tree->getAllNodes());
    }

}

# Testing methods run
$testCase = new TestMergeTree();
$testCase->run();
