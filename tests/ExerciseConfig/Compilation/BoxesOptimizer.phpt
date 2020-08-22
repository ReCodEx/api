<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesOptimizer;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;

/**
 * @testCase
 */
class TestBoxesOptimizer extends Tester\TestCase
{
    /** @var BoxesOptimizer */
    private $optimizer;

    public function __construct()
    {
        $this->optimizer = new BoxesOptimizer();
    }

    public function testSimpleTree()
    {
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

    public function testTwoSimpleTrees()
    {
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

    public function testTwoDistinctTrees()
    {
        $nodeA1 = (new Node())->setBox(
            (new CustomBox("nodeA1"))->addOutputPort(new Port(PortMeta::create("", "string")))
        );
        $nodeA2 = (new Node())->setBox(
            (new CustomBox("nodeA2"))->addInputPort(new Port(PortMeta::create("", "string")))
        );
        $nodeA1->addChild($nodeA2);
        $nodeA2->addParent($nodeA1);

        $nodeB1 = (new Node())->setBox(
            (new CustomBox("nodeB1"))->addOutputPort(new Port(PortMeta::create("", "file")))
        );
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

    public function testTwoPartlySameTrees()
    {
        $nodeA1 = (new Node())->setBox(
            (new CustomBox("nodeA1"))->addOutputPort(new Port(PortMeta::create("", "string")))
        );
        $nodeA2 = (new Node())->setBox(
            (new CustomBox("nodeA2"))->addInputPort(new Port(PortMeta::create("", "string")))
        );
        $nodeA1->addChild($nodeA2);
        $nodeA2->addParent($nodeA1);

        $nodeB1 = (new Node())->setBox(
            (new CustomBox("nodeB1"))->addOutputPort(new Port(PortMeta::create("", "string")))
        );
        $nodeB2 = (new Node())->setBox(
            (new CustomBox("nodeB2"))
                ->addInputPort(new Port(PortMeta::create("", "string")))->addInputPort(
                    new Port(PortMeta::create("", "file"))
                )
        );
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

    public function testThreeComplexTrees()
    {
        $tests = [
            "A" => $this->buildCompileExecJudgeTree("A"),
            "B" => $this->buildCompileExecJudgeTree("B"),
            "C" => $this->buildCompileExecJudgeTree("C")
        ];

        $tree = $this->optimizer->optimize($tests);
        Assert::count(1, $tree->getRootNodes());

        $srcNode = $tree->getRootNodes()[0];
        Assert::equal("source", $srcNode->getBox()->getName());
        Assert::equal(null, $srcNode->getTestId());
        Assert::count(0, $srcNode->getParents());
        Assert::count(1, $srcNode->getChildren());

        $compNode = $srcNode->getChildren()[0];
        Assert::equal("compilation", $compNode->getBox()->getName());
        Assert::equal(null, $compNode->getTestId());
        Assert::count(1, $compNode->getParents());
        Assert::count(3, $compNode->getChildren());

        foreach ($compNode->getChildren() as $inNode) {
            $testId = $inNode->getTestId();
            Assert::equal("input", $inNode->getBox()->getName());
            Assert::notEqual(null, $inNode->getTestId());
            Assert::count(1, $inNode->getParents());
            Assert::count(1, $inNode->getChildren());

            $execNode = $inNode->getChildren()[0];
            Assert::equal("execution", $execNode->getBox()->getName());
            Assert::equal($testId, $execNode->getTestId());
            Assert::count(1, $execNode->getParents());
            Assert::count(1, $execNode->getChildren());

            $expectedNode = $execNode->getChildren()[0];
            Assert::equal("expectedOut", $expectedNode->getBox()->getName());
            Assert::equal($testId, $expectedNode->getTestId());
            Assert::count(1, $expectedNode->getParents());
            Assert::count(1, $expectedNode->getChildren());

            $judgeNode = $expectedNode->getChildren()[0];
            Assert::equal("judge", $judgeNode->getBox()->getName());
            Assert::equal($testId, $judgeNode->getTestId());
            Assert::count(1, $judgeNode->getParents());
            Assert::count(0, $judgeNode->getChildren());
        }
    }


    private function buildCompileExecJudgeTree(string $testId): RootedTree
    {
        $tree = new RootedTree();

        $sourceVar = new Variable(VariableTypes::$STRING_TYPE, "src", "source.src");
        $inputVar = new Variable(VariableTypes::$STRING_TYPE, "in", "input{$testId}.in");
        $binaryVar = new Variable(VariableTypes::$STRING_TYPE, "binary", "a.out");
        $outVar = new Variable(VariableTypes::$STRING_TYPE, "out", "file.out");
        $expectedOutVar = new Variable(VariableTypes::$STRING_TYPE, "eOut", "expected{$testId}.out");

        $source = (new Node())->setTestId($testId)->setBox(
            (new CustomBox("source"))
                ->addOutputPort((new Port(PortMeta::create("src", "string")))->setVariableValue($sourceVar))
        );
        $compilation = (new Node())->setTestId($testId)->setBox(
            (new CustomBox("compilation"))
                ->addInputPort((new Port(PortMeta::create("src", "string")))->setVariableValue($sourceVar))
                ->addOutputPort((new Port(PortMeta::create("binary", "string")))->setVariableValue($binaryVar))
        );
        $input = (new Node())->setTestId($testId)->setBox(
            (new CustomBox("input"))
                ->addOutputPort((new Port(PortMeta::create("in", "string")))->setVariableValue($inputVar))
        );
        $execution = (new Node())->setTestId($testId)->setBox(
            (new CustomBox("execution"))
                ->addInputPort((new Port(PortMeta::create("in", "string")))->setVariableValue($inputVar))
                ->addInputPort((new Port(PortMeta::create("binary", "string")))->setVariableValue($binaryVar))
                ->addOutputPort((new Port(PortMeta::create("out", "string")))->setVariableValue($outVar))
        );
        $expectedOut = (new Node())->setTestId($testId)->setBox(
            (new CustomBox("expectedOut"))
                ->addOutputPort(
                    (new Port(PortMeta::create("expectedOut", "string")))->setVariableValue($expectedOutVar)
                )
        );
        $judge = (new Node())->setTestId($testId)->setBox(
            (new CustomBox("judge"))
                ->addInputPort((new Port(PortMeta::create("out", "string")))->setVariableValue($outVar))
                ->addInputPort((new Port(PortMeta::create("expectedOut", "string")))->setVariableValue($expectedOutVar))
        );

        // relations definitions
        $source->addChild($compilation);
        $compilation->addParent($source);
        $compilation->addChild($input);
        $input->addParent($compilation);
        $input->addChild($execution);
        $execution->addParent($input);
        $execution->addChild($expectedOut);
        $expectedOut->addParent($execution);
        $expectedOut->addChild($judge);
        $judge->addParent($expectedOut);

        return $tree->addRootNode($source);
    }
}

# Testing methods run
$testCase = new TestBoxesOptimizer();
$testCase->run();
