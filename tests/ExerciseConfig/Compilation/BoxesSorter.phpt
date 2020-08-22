<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseCompilationException;
use App\Helpers\ExerciseConfig\Compilation\BoxesSorter;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use Tester\Assert;


class TestBoxesSorter extends Tester\TestCase
{
    /** @var BoxesSorter */
    private $sorter;

    public function __construct()
    {
        $this->sorter = new BoxesSorter();
    }


    public function testEmptyMergeTrees()
    {
        Assert::noError(
            function () {
                $this->sorter->sort(array());
            }
        );
    }

    /**
     *  A -> B
     */
    public function testSimpleA()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $B->addParent("BA", $A);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        Assert::count(0, $BNode->getChildren());
    }

    /**
     *  A -> B -> C
     */
    public function testSimpleB()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $B->addChild("BC", $C);
        $B->addParent("BA", $A);
        $C->addParent("CB", $B);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$BNode], $CNode->getDependencies());
        Assert::count(0, $CNode->getChildren());
    }

    /**
     *  A   B
     *   \ /
     *    C
     */
    public function testSimpleI()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());

        $A->addChild("AC", $C);
        $B->addChild("BC", $C);
        $C->addParent("CA", $A);
        $C->addParent("CB", $B);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$ANode, $BNode], $CNode->getDependencies());
        Assert::count(0, $CNode->getChildren());
    }

    /**
     *    A
     *   / \
     *  B   C
     */
    public function testSimpleC()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $A->addChild("AC", $C);
        $B->addParent("BA", $A);
        $C->addParent("CA", $A);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$ANode], $CNode->getDependencies());
        Assert::count(0, $CNode->getChildren());
    }

    /**
     *    A
     *   / \
     *  B   C
     *       \
     *        D
     */
    public function testSimpleD()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());
        $D = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $A->addChild("AC", $C);
        $B->addParent("BA", $A);
        $C->addChild("CD", $D);
        $C->addParent("CA", $A);
        $D->addParent("DC", $C);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$ANode], $CNode->getDependencies());
        $DNode = $CNode->getChildren()[0];
        Assert::same($D->getBox(), $DNode->getBox());
        Assert::equal([$CNode], $DNode->getDependencies());
        Assert::count(0, $DNode->getChildren());
    }

    /**
     *    A
     *   / \
     *  B   C
     *     / \
     *    D   E
     */
    public function testSimpleE()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());
        $D = new PortNode(new CustomBox());
        $E = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $A->addChild("AC", $C);
        $B->addParent("BA", $A);
        $C->addChild("CD", $D);
        $C->addChild("CE", $E);
        $C->addParent("CA", $A);
        $D->addParent("DC", $C);
        $E->addParent("EC", $C);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D)->addOtherNode($E);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$ANode], $CNode->getDependencies());
        $DNode = $CNode->getChildren()[0];
        Assert::same($D->getBox(), $DNode->getBox());
        Assert::equal([$CNode], $DNode->getDependencies());
        $ENode = $DNode->getChildren()[0];
        Assert::same($E->getBox(), $ENode->getBox());
        Assert::equal([$CNode], $ENode->getDependencies());
        Assert::count(0, $ENode->getChildren());
    }

    /**
     *  A
     *   \
     *    B
     *   /|\
     *  C D E
     */
    public function testSimpleF()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());
        $D = new PortNode(new CustomBox());
        $E = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $B->addChild("BC", $C);
        $B->addChild("BD", $D);
        $B->addChild("BE", $E);
        $B->addParent("BA", $A);
        $C->addParent("CB", $B);
        $D->addParent("DB", $B);
        $E->addParent("EB", $B);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D)->addOtherNode($E);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$BNode], $CNode->getDependencies());
        $DNode = $CNode->getChildren()[0];
        Assert::same($D->getBox(), $DNode->getBox());
        Assert::equal([$BNode], $DNode->getDependencies());
        $ENode = $DNode->getChildren()[0];
        Assert::same($E->getBox(), $ENode->getBox());
        Assert::equal([$BNode], $ENode->getDependencies());
        Assert::count(0, $ENode->getChildren());
    }

    /**
     *    A
     *   / \
     *  B   C
     *   \ /
     *    D
     */
    public function testSimpleG()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());
        $D = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $A->addChild("AC", $C);
        $B->addChild("BD", $D);
        $B->addParent("BA", $A);
        $C->addChild("CD", $D);
        $C->addParent("CA", $A);
        $D->addParent("DB", $B);
        $D->addParent("DC", $C);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$ANode], $CNode->getDependencies());
        $DNode = $CNode->getChildren()[0];
        Assert::same($D->getBox(), $DNode->getBox());
        Assert::equal([$BNode, $CNode], $DNode->getDependencies());
        Assert::count(0, $DNode->getChildren());
    }

    /**
     *    A
     *   / \
     *  B   C
     *   \ / \
     *    D   E
     */
    public function testSimpleH()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());
        $D = new PortNode(new CustomBox());
        $E = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $A->addChild("AC", $C);
        $B->addChild("BD", $D);
        $B->addParent("BA", $A);
        $C->addChild("CD", $D);
        $C->addChild("CE", $E);
        $C->addParent("CA", $A);
        $D->addParent("DB", $B);
        $D->addParent("DC", $C);
        $E->addParent("EC", $C);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D)->addOtherNode($E);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$ANode], $CNode->getDependencies());
        $DNode = $CNode->getChildren()[0];
        Assert::same($D->getBox(), $DNode->getBox());
        Assert::equal([$BNode, $CNode], $DNode->getDependencies());
        $ENode = $DNode->getChildren()[0];
        Assert::same($E->getBox(), $ENode->getBox());
        Assert::equal([$CNode], $ENode->getDependencies());
        Assert::count(0, $ENode->getChildren());
    }

    /**
     *    A
     *   / \
     *  B   C
     *  |   |
     *  D   E
     */
    public function testSimpleJ()
    {
        $A = new PortNode(new CustomBox("A"));
        $B = new PortNode(new CustomBox("B"));
        $C = new PortNode(new CustomBox("C"));
        $D = new PortNode(new CustomBox("D"));
        $E = new PortNode(new CustomBox("E"));

        $A->addChild("AB", $B);
        $A->addChild("AC", $C);
        $B->addParent("BA", $A);
        $B->addChild("BD", $D);
        $C->addParent("CA", $A);
        $C->addChild("CE", $E);
        $D->addParent("DB", $B);
        $E->addParent("EC", $C);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D)->addOtherNode($E);
        $treeArr = array($tree);

        // sort
        $result = $this->sorter->sort($treeArr)[0];

        // *** check order of nodes
        $ANode = $result->getRootNodes()[0];
        Assert::same($A->getBox(), $ANode->getBox());
        Assert::equal([], $ANode->getDependencies());
        $BNode = $ANode->getChildren()[0];
        Assert::same($B->getBox(), $BNode->getBox());
        Assert::equal([$ANode], $BNode->getDependencies());
        $CNode = $BNode->getChildren()[0];
        Assert::same($C->getBox(), $CNode->getBox());
        Assert::equal([$ANode], $CNode->getDependencies());
        $DNode = $CNode->getChildren()[0];
        Assert::same($D->getBox(), $DNode->getBox());
        Assert::equal([$BNode], $DNode->getDependencies());
        $ENode = $DNode->getChildren()[0];
        Assert::same($E->getBox(), $ENode->getBox());
        Assert::equal([$CNode], $ENode->getDependencies());
        Assert::count(0, $ENode->getChildren());
    }

    /**
     *         A
     *        / \
     *  ---> B   D
     *  |     \ /
     *  |      C
     *  |     /
     *  ---- E
     */
    public function testCycleA()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());
        $D = new PortNode(new CustomBox());
        $E = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $A->addChild("AD", $D);
        $B->addChild("BC", $C);
        $B->addParent("BA", $A);
        $B->addParent("BE", $E);
        $C->addChild("CE", $E);
        $C->addParent("CB", $B);
        $C->addParent("CD", $D);
        $D->addChild("DC", $C);
        $D->addParent("DA", $A);
        $E->addChild("EB", $B);
        $E->addParent("EC", $C);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D)->addOtherNode($E);
        $treeArr = array($tree);

        Assert::exception(
            function () use ($treeArr) {
                $this->sorter->sort($treeArr);
            },
            ExerciseCompilationException::class
        );
    }

    /**
     *  A -> B
     *  ^    |
     *  |    |
     *  D <- C
     */
    public function testCycleB()
    {
        $A = new PortNode(new CustomBox());
        $B = new PortNode(new CustomBox());
        $C = new PortNode(new CustomBox());
        $D = new PortNode(new CustomBox());

        $A->addChild("AB", $B);
        $A->addParent("AD", $D);
        $B->addChild("BC", $C);
        $B->addParent("BA", $A);
        $C->addChild("CD", $D);
        $C->addParent("CB", $B);
        $D->addChild("DA", $A);
        $D->addParent("DC", $C);

        $tree = new MergeTree();
        $tree->addOtherNode($A)->addOtherNode($B)->addOtherNode($C)->addOtherNode($D);
        $treeArr = array($tree);

        Assert::exception(
            function () use ($treeArr) {
                $this->sorter->sort($treeArr);
            },
            ExerciseCompilationException::class
        );
    }

}

# Testing methods run
$testCase = new TestBoxesSorter();
$testCase->run();
