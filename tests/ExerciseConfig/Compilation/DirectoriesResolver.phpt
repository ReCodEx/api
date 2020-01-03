<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\CompilationContext;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Compilation\DirectoriesResolver;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Nette\Utils\Strings;
use Tester\Assert;


class TestDirectoriesResolver extends Tester\TestCase
{
    /** @var DirectoriesResolver */
    private $resolver;

    public function __construct()
    {
        $this->resolver = new DirectoriesResolver();
    }

    public function testCorrect()
    {
        $varAB = new Variable(VariableTypes::$FILE_TYPE, "varAB", "valAB");
        $varBC = new Variable(VariableTypes::$FILE_TYPE, "varBC", "valBC");
        $varCD = new Variable(VariableTypes::$FILE_TYPE, "varCD", "valCD");
        $varCE = new Variable(VariableTypes::$FILE_ARRAY_TYPE, "varCE", ["valCE1", "valCE2"]);
        $varEH = new Variable(VariableTypes::$FILE_TYPE, "varEH", "valEH");
        $varAF = new Variable(VariableTypes::$FILE_TYPE, "varAF", "valAF");
        $varFG = new Variable(VariableTypes::$FILE_TYPE, "varFG", "valFG");

        $portA1 = (new Port(PortMeta::create("portAB", VariableTypes::$FILE_TYPE)))->setVariableValue($varAB);
        $portA2 = (new Port(PortMeta::create("portAF", VariableTypes::$FILE_TYPE)))->setVariableValue($varAF);
        $A = (new Node())->setBox((new CustomBox("A"))->addOutputPort($portA1)->addOutputPort($portA2));

        $portB1 = (new Port(PortMeta::create("portAB", VariableTypes::$FILE_TYPE)))->setVariableValue($varAB);
        $portB2 = (new Port(PortMeta::create("portBC", VariableTypes::$FILE_TYPE)))->setVariableValue($varBC);
        $B = (new Node())->setBox((new CustomBox("B"))->addInputPort($portB1)->addOutputPort($portB2));
        $B->setTestId("1");

        $portC1 = (new Port(PortMeta::create("portBC", VariableTypes::$FILE_TYPE)))->setVariableValue($varBC);
        $portC2 = (new Port(PortMeta::create("portCD", VariableTypes::$FILE_TYPE)))->setVariableValue($varCD);
        $portC3 = (new Port(PortMeta::create("portCE", VariableTypes::$FILE_ARRAY_TYPE)))->setVariableValue($varCE);
        $C = (new Node())->setBox(
            (new CustomBox("C"))->addInputPort($portC1)->addOutputPort($portC2)->addOutputPort($portC3)
        );
        $C->setTestId("1");

        $portD1 = (new Port(PortMeta::create("portCD", VariableTypes::$FILE_TYPE)))->setVariableValue($varCD);
        $D = (new Node())->setBox((new CustomBox("D"))->addInputPort($portD1));
        $D->setTestId("1");

        $portE1 = (new Port(PortMeta::create("portCE", VariableTypes::$FILE_ARRAY_TYPE)))->setVariableValue($varCE);
        $portE2 = (new Port(PortMeta::create("portEH", VariableTypes::$FILE_TYPE)))->setVariableValue($varEH);
        $E = (new Node())->setBox((new CustomBox("E"))->addInputPort($portE1)->addOutputPort($portE2));

        $varF2 = new Variable(VariableTypes::$STRING_TYPE, "varF2", "valF2");
        $portF1 = (new Port(PortMeta::create("portAF", VariableTypes::$FILE_TYPE)))->setVariableValue($varAF);
        $portF2 = (new Port(PortMeta::create("portF2", VariableTypes::$STRING_TYPE)))->setVariableValue($varF2);
        $portF3 = (new Port(PortMeta::create("portFG", VariableTypes::$FILE_TYPE)))->setVariableValue($varFG);
        $F = (new Node())->setBox(
            (new CustomBox("F"))->addInputPort($portF1)->addInputPort($portF2)->addOutputPort($portF3)
        );
        $F->setTestId("2");

        $portG1 = (new Port(PortMeta::create("portFG", VariableTypes::$STRING_TYPE)))->setVariableValue($varFG);
        $G = (new Node())->setBox((new CustomBox("G"))->addInputPort($portG1));
        $G->setTestId("2");

        $portH1 = (new Port(PortMeta::create("portEH", VariableTypes::$FILE_TYPE)))->setVariableValue($varEH);
        $H = (new Node())->setBox((new CustomBox("H"))->addInputPort($portH1));

        /*
         *    B - C - D
         *  /      \
         * A        E - H
         *  \
         *   F - G
         */
        $A->addChild($B);
        $A->addChild($F);
        $B->addParent($A);
        $B->addChild($C);
        $C->addParent($B);
        $C->addChild($D);
        $C->addChild($E);
        $D->addParent($C);
        $E->addParent($C);
        $E->addChild($H);
        $F->addParent($A);
        $F->addChild($G);
        $G->addParent($F);
        $H->addParent($E);


        $tree = new RootedTree();
        $tree->addRootNode($A);

        $testsNames = [
            "1" => "testA",
            "2" => "testB"
        ];

        // execute and assert
        $context = CompilationContext::create(new ExerciseConfig(), new VariablesTable(), [], [], $testsNames, "");
        $params = CompilationParams::create();
        $result = $this->resolver->resolve($tree, $context, $params);
        Assert::count(1, $result->getRootNodes());

        $mkdirCustomA = $result->getRootNodes()[0];
        Assert::count(0, $mkdirCustomA->getParents());
        Assert::count(1, $mkdirCustomA->getChildren());
        Assert::count(0, $mkdirCustomA->getDependencies());
        Assert::equal(null, $mkdirCustomA->getTestId());
        Assert::true(Strings::startsWith($mkdirCustomA->getBox()->getDirectory(), "custom_"));
        Assert::equal(17, strlen($mkdirCustomA->getBox()->getDirectory()));
        Assert::equal("mkdir", $mkdirCustomA->getBox()->getType());
        Assert::count(1, $mkdirCustomA->getBox()->getInputPorts());
        Assert::true(
            Strings::startsWith(
                current($mkdirCustomA->getBox()->getInputPorts())->getVariableValue()->getValue(),
                "custom_"
            )
        );
        Assert::count(0, $mkdirCustomA->getBox()->getOutputPorts());

        $mkdirB = $mkdirCustomA->getChildren()[0];
        Assert::count(1, $mkdirB->getParents());
        Assert::equal([$mkdirCustomA], $mkdirB->getParents());
        Assert::count(1, $mkdirB->getChildren());
        Assert::count(0, $mkdirB->getDependencies());
        Assert::equal(null, $mkdirB->getTestId());
        Assert::equal("testB", $mkdirB->getBox()->getDirectory());
        Assert::equal("mkdir", $mkdirB->getBox()->getType());
        Assert::count(1, $mkdirB->getBox()->getInputPorts());
        Assert::equal("testB", current($mkdirB->getBox()->getInputPorts())->getVariableValue()->getValue());
        Assert::count(0, $mkdirB->getBox()->getOutputPorts());

        $mkdirA = $mkdirB->getChildren()[0];
        Assert::count(1, $mkdirA->getParents());
        Assert::equal([$mkdirB], $mkdirA->getParents());
        Assert::count(1, $mkdirA->getChildren());
        Assert::count(0, $mkdirA->getDependencies());
        Assert::equal(null, $mkdirA->getTestId());
        Assert::equal("testA", $mkdirA->getBox()->getDirectory());
        Assert::equal("mkdir", $mkdirA->getBox()->getType());
        Assert::count(1, $mkdirA->getBox()->getInputPorts());
        Assert::equal("testA", current($mkdirA->getBox()->getInputPorts())->getVariableValue()->getValue());
        Assert::count(0, $mkdirA->getBox()->getOutputPorts());

        $mkdirCustomB = $mkdirA->getChildren()[0];
        Assert::count(1, $mkdirCustomB->getParents());
        Assert::equal([$mkdirA], $mkdirCustomB->getParents());
        Assert::count(1, $mkdirCustomB->getChildren());
        Assert::equal([$A], $mkdirCustomB->getChildren());
        Assert::count(0, $mkdirCustomB->getDependencies());
        Assert::equal(null, $mkdirCustomB->getTestId());
        Assert::true(Strings::startsWith($mkdirCustomB->getBox()->getDirectory(), "custom_custom_"));
        Assert::equal(24, strlen($mkdirCustomB->getBox()->getDirectory()));
        Assert::equal("mkdir", $mkdirCustomB->getBox()->getType());
        Assert::count(1, $mkdirCustomB->getBox()->getInputPorts());
        Assert::true(
            Strings::startsWith(
                current($mkdirCustomB->getBox()->getInputPorts())->getVariableValue()->getValue(),
                "custom_"
            )
        );
        Assert::count(0, $mkdirCustomB->getBox()->getOutputPorts());

        $Adir = $mkdirCustomA->getBox()->getDirectory();
        Assert::count(1, $A->getParents());
        Assert::equal([$mkdirCustomB], $A->getParents());
        Assert::count(2, $A->getChildren());
        Assert::count(1, $A->getDependencies());
        Assert::equal([$mkdirCustomA], $A->getDependencies());
        Assert::equal(null, $A->getTestId());
        Assert::equal($Adir, $A->getBox()->getDirectory());
        Assert::equal("A", $A->getBox()->getName());
        Assert::count(0, $A->getBox()->getInputPorts());
        Assert::count(2, $A->getBox()->getOutputPorts());
        Assert::equal(
            $Adir . "/valAB",
            $A->getBox()->getOutputPort("portAB")->getVariableValue()->getDirPrefixedValue()
        );
        Assert::equal(
            $Adir . "/valAF",
            $A->getBox()->getOutputPort("portAF")->getVariableValue()->getDirPrefixedValue()
        );

        // first copy child of A (AF)
        $AFcopy = $A->getChildren()[0];
        Assert::count(1, $AFcopy->getParents());
        Assert::equal([$A], $AFcopy->getParents());
        Assert::count(1, $AFcopy->getChildren());
        Assert::equal([$F], $AFcopy->getChildren());
        Assert::count(1, $AFcopy->getDependencies());
        Assert::equal([$mkdirB], $AFcopy->getDependencies());
        Assert::equal(null, $AFcopy->getTestId());
        Assert::equal("testB", $AFcopy->getBox()->getDirectory());
        Assert::equal("copy-file", $AFcopy->getBox()->getName());
        Assert::count(1, $AFcopy->getBox()->getInputPorts());
        Assert::equal(
            $Adir . "/valAF",
            current($AFcopy->getBox()->getInputPorts())->getVariableValue()->getDirPrefixedValue()
        );
        Assert::count(1, $AFcopy->getBox()->getOutputPorts());
        Assert::equal(
            "testB/valAF",
            current($AFcopy->getBox()->getOutputPorts())->getVariableValue()->getDirPrefixedValue()
        );

        // second copy child of A (AB)
        $ABcopy = $A->getChildren()[1];
        Assert::count(1, $ABcopy->getParents());
        Assert::equal([$A], $ABcopy->getParents());
        Assert::count(1, $ABcopy->getChildren());
        Assert::equal([$B], $ABcopy->getChildren());
        Assert::count(1, $ABcopy->getDependencies());
        Assert::equal([$mkdirA], $ABcopy->getDependencies());
        Assert::equal(null, $ABcopy->getTestId());
        Assert::equal("testA", $ABcopy->getBox()->getDirectory());
        Assert::equal("copy-file", $ABcopy->getBox()->getName());
        Assert::count(1, $ABcopy->getBox()->getInputPorts());
        Assert::equal(
            $Adir . "/valAB",
            current($ABcopy->getBox()->getInputPorts())->getVariableValue()->getDirPrefixedValue()
        );
        Assert::count(1, $ABcopy->getBox()->getOutputPorts());
        Assert::equal(
            "testA/valAB",
            current($ABcopy->getBox()->getOutputPorts())->getVariableValue()->getDirPrefixedValue()
        );

        Assert::count(1, $B->getParents());
        Assert::equal([$ABcopy], $B->getParents());
        Assert::count(1, $B->getChildren());
        Assert::equal([$C], $B->getChildren());
        Assert::count(2, $B->getDependencies());
        Assert::equal([$mkdirA, $ABcopy], $B->getDependencies());
        Assert::equal("1", $B->getTestId());
        Assert::equal("testA", $B->getBox()->getDirectory());
        Assert::equal("B", $B->getBox()->getName());
        Assert::count(1, $B->getBox()->getInputPorts());
        Assert::equal("testA/valAB", current($B->getBox()->getInputPorts())->getVariableValue()->getDirPrefixedValue());
        Assert::count(1, $B->getBox()->getOutputPorts());
        Assert::equal(
            "testA/valBC",
            current($B->getBox()->getOutputPorts())->getVariableValue()->getDirPrefixedValue()
        );

        Assert::count(1, $C->getParents());
        Assert::equal([$B], $C->getParents());
        Assert::count(2, $C->getChildren());
        Assert::contains($D, $C->getChildren());
        Assert::count(1, $C->getDependencies());
        Assert::equal([$mkdirA], $C->getDependencies());
        Assert::equal("1", $C->getTestId());
        Assert::equal("testA", $C->getBox()->getDirectory());
        Assert::equal("C", $C->getBox()->getName());
        Assert::count(1, $C->getBox()->getInputPorts());
        Assert::equal("testA/valBC", $C->getBox()->getInputPort("portBC")->getVariableValue()->getDirPrefixedValue());
        Assert::count(2, $C->getBox()->getOutputPorts());
        Assert::equal("testA/valCD", $C->getBox()->getOutputPort("portCD")->getVariableValue()->getDirPrefixedValue());
        Assert::equal(
            ["testA/valCE1", "testA/valCE2"],
            $C->getBox()->getOutputPort("portCE")->getVariableValue()->getDirPrefixedValue()
        );

        Assert::count(1, $D->getParents());
        Assert::equal([$C], $D->getParents());
        Assert::count(0, $D->getChildren());
        Assert::count(1, $D->getDependencies());
        Assert::equal([$mkdirA], $D->getDependencies());
        Assert::equal("1", $D->getTestId());
        Assert::equal("testA", $D->getBox()->getDirectory());
        Assert::equal("D", $D->getBox()->getName());
        Assert::count(1, $D->getBox()->getInputPorts());
        Assert::equal("testA/valCD", $D->getBox()->getInputPort("portCD")->getVariableValue()->getDirPrefixedValue());
        Assert::count(0, $D->getBox()->getOutputPorts());

        // copy child of C (CE)
        $CEcopy = $C->getChildren()[1];
        Assert::count(1, $CEcopy->getParents());
        Assert::equal([$C], $CEcopy->getParents());
        Assert::count(1, $CEcopy->getChildren());
        Assert::equal([$E], $CEcopy->getChildren());
        Assert::count(1, $CEcopy->getDependencies());
        Assert::equal([$mkdirCustomB], $CEcopy->getDependencies());
        Assert::equal(null, $CEcopy->getTestId());
        Assert::equal($mkdirCustomB->getBox()->getDirectory(), $CEcopy->getBox()->getDirectory());
        Assert::equal("copy-files", $CEcopy->getBox()->getName());
        Assert::count(1, $CEcopy->getBox()->getInputPorts());
        Assert::equal(
            ["testA/valCE1", "testA/valCE2"],
            current($CEcopy->getBox()->getInputPorts())->getVariableValue()->getDirPrefixedValue()
        );
        Assert::count(1, $CEcopy->getBox()->getOutputPorts());
        Assert::equal(
            [$mkdirCustomB->getBox()->getDirectory() . "/valCE1", $mkdirCustomB->getBox()->getDirectory() . "/valCE2"],
            current($CEcopy->getBox()->getOutputPorts())->getVariableValue()->getDirPrefixedValue()
        );

        $Edir = $mkdirCustomB->getBox()->getDirectory();
        Assert::count(1, $E->getParents());
        Assert::equal([$CEcopy], $E->getParents());
        Assert::count(1, $E->getChildren());
        Assert::equal([$H], $E->getChildren());
        Assert::count(2, $E->getDependencies());
        Assert::equal([$mkdirCustomB, $CEcopy], $E->getDependencies());
        Assert::equal(null, $E->getTestId());
        Assert::equal($Edir, $E->getBox()->getDirectory());
        Assert::equal("E", $E->getBox()->getName());
        Assert::count(1, $E->getBox()->getInputPorts());
        Assert::equal(
            [$Edir . "/valCE1", $Edir . "/valCE2"],
            current($E->getBox()->getInputPorts())->getVariableValue()->getDirPrefixedValue()
        );
        Assert::count(1, $E->getBox()->getOutputPorts());
        Assert::equal(
            $Edir . "/valEH",
            current($E->getBox()->getOutputPorts())->getVariableValue()->getDirPrefixedValue()
        );

        $Hdir = $mkdirCustomB->getBox()->getDirectory();
        Assert::count(1, $H->getParents());
        Assert::equal([$E], $H->getParents());
        Assert::count(0, $H->getChildren());
        Assert::count(1, $H->getDependencies());
        Assert::equal([$mkdirCustomB], $H->getDependencies());
        Assert::equal(null, $H->getTestId());
        Assert::equal($Hdir, $H->getBox()->getDirectory());
        Assert::equal("H", $H->getBox()->getName());
        Assert::count(1, $H->getBox()->getInputPorts());
        Assert::equal(
            $Hdir . "/valEH",
            current($H->getBox()->getInputPorts())->getVariableValue()->getDirPrefixedValue()
        );
        Assert::count(0, $H->getBox()->getOutputPorts());

        Assert::count(1, $F->getParents());
        Assert::equal([$AFcopy], $F->getParents());
        Assert::count(1, $F->getChildren());
        Assert::equal([$G], $F->getChildren());
        Assert::count(2, $F->getDependencies());
        Assert::equal([$mkdirB, $AFcopy], $F->getDependencies());
        Assert::equal("2", $F->getTestId());
        Assert::equal("testB", $F->getBox()->getDirectory());
        Assert::equal("F", $F->getBox()->getName());
        Assert::count(2, $F->getBox()->getInputPorts());
        Assert::equal(
            "testB/valAF",
            $F->getBox()->getInputPorts()["portAF"]->getVariableValue()->getDirPrefixedValue()
        );
        Assert::equal("valF2", $F->getBox()->getInputPorts()["portF2"]->getVariableValue()->getValue());
        Assert::count(1, $F->getBox()->getOutputPorts());
        Assert::equal(
            "testB/valFG",
            $F->getBox()->getOutputPorts()["portFG"]->getVariableValue()->getDirPrefixedValue()
        );

        Assert::count(1, $G->getParents());
        Assert::equal([$F], $G->getParents());
        Assert::count(0, $G->getChildren());
        Assert::count(1, $G->getDependencies());
        Assert::equal([$mkdirB], $G->getDependencies());
        Assert::equal("2", $G->getTestId());
        Assert::equal("testB", $G->getBox()->getDirectory());
        Assert::equal("G", $G->getBox()->getName());
        Assert::count(1, $G->getBox()->getInputPorts());
        Assert::equal(
            "testB/valFG",
            $G->getBox()->getInputPorts()["portFG"]->getVariableValue()->getDirPrefixedValue()
        );
        Assert::count(0, $G->getBox()->getOutputPorts());
    }

}

# Testing methods run
$testCase = new TestDirectoriesResolver();
$testCase->run();
