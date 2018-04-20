<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\CompilationContext;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Compilation\TestDirectoriesResolver;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;


class TestTestDirectoriesResolver extends Tester\TestCase
{
  /** @var TestDirectoriesResolver */
  private $resolver;

  public function __construct() {
    $this->resolver = new TestDirectoriesResolver();
  }

  public function testCorrect() {

    $varA = new Variable(VariableTypes::$FILE_TYPE, "varA", "valA");
    $portA = (new Port(PortMeta::create("portA", VariableTypes::$FILE_TYPE)))->setVariableValue($varA);
    $A = (new Node)->setBox((new CustomBox("A"))->addOutputPort($portA));

    $varB = new Variable(VariableTypes::$FILE_TYPE, "varB", "valB");
    $portB = (new Port(PortMeta::create("portB", VariableTypes::$FILE_TYPE)))->setVariableValue($varB);
    $B = (new Node)->setBox((new CustomBox("B"))->addOutputPort($portB));
    $B->setTestId("1");

    $varC = new Variable(VariableTypes::$FILE_ARRAY_TYPE, "varC", ["valC1", "valC2"]);
    $portC = (new Port(PortMeta::create("portC", VariableTypes::$FILE_ARRAY_TYPE)))->setVariableValue($varC);
    $C = (new Node)->setBox((new CustomBox("C"))->addOutputPort($portC));
    $C->setTestId("1");

    $varD = new Variable(VariableTypes::$REMOTE_FILE_TYPE, "varD", "valD");
    $portD = (new Port(PortMeta::create("portD", VariableTypes::$FILE_TYPE)))->setVariableValue($varD);
    $D = (new Node)->setBox((new CustomBox("D"))->addOutputPort($portD));
    $D->setTestId("1");

    $varE = new Variable(VariableTypes::$FILE_TYPE, "varE", "valE");
    $portE = (new Port(PortMeta::create("portE", VariableTypes::$FILE_TYPE)))->setVariableValue($varE);
    $E = (new Node)->setBox((new CustomBox("E"))->addOutputPort($portE));

    $varF1 = new Variable(VariableTypes::$FILE_TYPE, "varF1", "valF1");
    $varF2 = new Variable(VariableTypes::$FILE_TYPE, "varF2", "valF2");
    $portF1 = (new Port(PortMeta::create("portF1", VariableTypes::$FILE_TYPE)))->setVariableValue($varF1);
    $portF2 = (new Port(PortMeta::create("portF2", VariableTypes::$FILE_TYPE)))->setVariableValue($varF2);
    $F = (new Node)->setBox((new CustomBox("F"))->addOutputPort($portF1)->addOutputPort($portF2));
    $F->setTestId("2");

    $varG = new Variable(VariableTypes::$STRING_TYPE, "varG", "valG");
    $portG = (new Port(PortMeta::create("portG", VariableTypes::$STRING_TYPE)))->setVariableValue($varG);
    $G = (new Node)->setBox((new CustomBox("G"))->addOutputPort($portG));
    $G->setTestId("2");

    /*
     *    B - C - D
     *  /      \
     * A        E
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
    $F->addParent($A);
    $F->addChild($G);
    $G->addParent($F);


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

    $mkdirA = $result->getRootNodes()[0];
    Assert::count(0, $mkdirA->getParents());
    Assert::count(1, $mkdirA->getChildren());
    Assert::count(0, $mkdirA->getDependencies());
    Assert::equal("1", $mkdirA->getTestId());
    Assert::equal("mkdir", $mkdirA->getBox()->getType());
    Assert::count(1, $mkdirA->getBox()->getInputPorts());
    Assert::equal("testA", current($mkdirA->getBox()->getInputPorts())->getVariableValue()->getValue());

    $mkdirB = $mkdirA->getChildren()[0];
    Assert::count(1, $mkdirB->getParents());
    Assert::equal([$mkdirA], $mkdirB->getParents());
    Assert::count(1, $mkdirB->getChildren());
    Assert::equal([$A], $mkdirB->getChildren());
    Assert::count(0, $mkdirB->getDependencies());
    Assert::equal("2", $mkdirB->getTestId());
    Assert::equal("mkdir", $mkdirB->getBox()->getType());
    Assert::count(1, $mkdirB->getBox()->getInputPorts());
    Assert::equal("testB", current($mkdirB->getBox()->getInputPorts())->getVariableValue()->getValue());

    Assert::count(1, $A->getParents());
    Assert::equal([$mkdirB], $A->getParents());
    Assert::count(2, $A->getChildren());
    Assert::equal([$B, $F], $A->getChildren());
    Assert::count(0, $A->getDependencies());
    Assert::equal(null, $A->getTestId());
    Assert::equal("A", $A->getBox()->getName());
    Assert::count(1, $A->getBox()->getOutputPorts());
    Assert::equal("valA", current($A->getBox()->getOutputPorts())->getVariableValue()->getTestPrefixedValue());

    Assert::count(1, $B->getParents());
    Assert::equal([$A], $B->getParents());
    Assert::count(1, $B->getChildren());
    Assert::equal([$C], $B->getChildren());
    Assert::count(1, $B->getDependencies());
    Assert::equal([$mkdirA], $B->getDependencies());
    Assert::equal("1", $B->getTestId());
    Assert::equal("B", $B->getBox()->getName());
    Assert::count(1, $B->getBox()->getOutputPorts());
    Assert::equal("testA/valB", current($B->getBox()->getOutputPorts())->getVariableValue()->getTestPrefixedValue());

    Assert::count(1, $C->getParents());
    Assert::equal([$B], $C->getParents());
    Assert::count(2, $C->getChildren());
    Assert::equal([$D, $E], $C->getChildren());
    Assert::count(1, $C->getDependencies());
    Assert::equal([$mkdirA], $C->getDependencies());
    Assert::equal("1", $C->getTestId());
    Assert::equal("C", $C->getBox()->getName());
    Assert::count(1, $C->getBox()->getOutputPorts());
    Assert::equal(["testA/valC1", "testA/valC2"], current($C->getBox()->getOutputPorts())->getVariableValue()->getTestPrefixedValue());

    Assert::count(1, $D->getParents());
    Assert::equal([$C], $D->getParents());
    Assert::count(0, $D->getChildren());
    Assert::count(1, $D->getDependencies());
    Assert::equal([$mkdirA], $D->getDependencies());
    Assert::equal("1", $D->getTestId());
    Assert::equal("D", $D->getBox()->getName());
    Assert::count(1, $D->getBox()->getOutputPorts());
    Assert::equal("valD", current($D->getBox()->getOutputPorts())->getVariableValue()->getTestPrefixedValue());

    Assert::count(1, $E->getParents());
    Assert::equal([$C], $E->getParents());
    Assert::count(0, $E->getChildren());
    Assert::count(0, $E->getDependencies());
    Assert::equal(null, $E->getTestId());
    Assert::equal("E", $E->getBox()->getName());
    Assert::count(1, $E->getBox()->getOutputPorts());
    Assert::equal("valE", current($E->getBox()->getOutputPorts())->getVariableValue()->getTestPrefixedValue());

    Assert::count(1, $F->getParents());
    Assert::equal([$A], $F->getParents());
    Assert::count(1, $F->getChildren());
    Assert::equal([$G], $F->getChildren());
    Assert::count(1, $F->getDependencies());
    Assert::equal([$mkdirB], $F->getDependencies());
    Assert::equal("2", $F->getTestId());
    Assert::equal("F", $F->getBox()->getName());
    Assert::count(2, $F->getBox()->getOutputPorts());
    Assert::equal("testB/valF1", $F->getBox()->getOutputPorts()["portF1"]->getVariableValue()->getTestPrefixedValue());
    Assert::equal("testB/valF2", $F->getBox()->getOutputPorts()["portF2"]->getVariableValue()->getTestPrefixedValue());

    Assert::count(1, $G->getParents());
    Assert::equal([$F], $G->getParents());
    Assert::count(0, $G->getChildren());
    Assert::count(1, $G->getDependencies());
    Assert::equal([$mkdirB], $G->getDependencies());
    Assert::equal("2", $G->getTestId());
    Assert::equal("G", $G->getBox()->getName());
    Assert::count(1, $G->getBox()->getOutputPorts());
    Assert::equal("valG", current($G->getBox()->getOutputPorts())->getVariableValue()->getTestPrefixedValue());
  }

}

# Testing methods run
$testCase = new TestTestDirectoriesResolver();
$testCase->run();
