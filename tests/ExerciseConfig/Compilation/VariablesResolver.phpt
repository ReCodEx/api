<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableMeta;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;


class TestVariablesResolver extends Tester\TestCase
{
  /** @var VariablesResolver */
  private $resolver;

  /** @var MergeTree[] */
  private $treeArray = array();

  /** @var VariablesTable */
  private $envVarTableA;
  /** @var VariablesTable */
  private $exerVarTableA;
  /** @var VariablesTable */
  private $pipeVarTableA;

  /** @var VariablesTable */
  private $envVarTableB;
  /** @var VariablesTable */
  private $exerVarTableB;
  /** @var VariablesTable */
  private $pipeVarTableB;


  public function __construct() {
    $this->resolver = new VariablesResolver();
  }

  protected function setUp() {

    //
    // Tree A -> pipeline: in -> exec -> out; pre-exec -> exec
    //

    $referencedVarA = new Variable((new VariableMeta)->setName("test-a-reference-variable")->setType("file")->setValue("booya"));
    $testInputVarA = new Variable((new VariableMeta)->setName("test-a-input")->setType("file")->setValue("infile"));
    $testInputArrayVarA = new Variable((new VariableMeta)->setName("test-a-input-array")->setType("file[]")->setValue("in*"));
    $preExecVarA = new Variable((new VariableMeta)->setName("test-a-pre-exec")->setType("file")->setValue('$test-a-reference-variable'));
    $outputReferencedVarA = new Variable((new VariableMeta)->setName("test-a-output-reference")->setType("file")->setValue("yaboo"));
    $testOutputVarA = new Variable((new VariableMeta)->setName("test-a-output")->setType("file")->setValue('$test-a-output-reference'));

    $this->envVarTableA = (new VariablesTable)->set($outputReferencedVarA)->set($testInputVarA)->set($testInputArrayVarA);
    $this->exerVarTableA = (new VariablesTable)->set($referencedVarA);
    $this->pipeVarTableA = (new VariablesTable)->set($testInputVarA)->set($testInputArrayVarA)->set($testOutputVarA)->set($preExecVarA);

    $outPortA = new Port((new PortMeta)->setName("data-in")->setType(VariableTypes::$FILE_TYPE)->setVariable($testInputVarA->getName()));
    $dataInNodeA = new PortNode((new CustomBox)->setName("in")->addOutputPort($outPortA));

    $outPortArrayA = new Port((new PortMeta)->setName("data-in-arr")->setType(VariableTypes::$FILE_TYPE)->setVariable($testInputArrayVarA->getName()));
    $dataInNodeArrayA = new PortNode((new CustomBox)->setName("in-arr")->addOutputPort($outPortArrayA));

    $preExecPortA = new Port((new PortMeta)->setName("pre-data")->setType(VariableTypes::$FILE_TYPE)->setVariable($preExecVarA->getName()));
    $preExecNodeA = new PortNode((new CustomBox)->setName("pre-exec")->addOutputPort($preExecPortA));

    $inPortA = new Port((new PortMeta)->setName("data-out")->setType(VariableTypes::$FILE_TYPE)->setVariable($testOutputVarA->getName()));
    $execNodeA = new PortNode((new CustomBox)->setName("exec")
      ->addInputPort($preExecPortA)->addInputPort($outPortA)->addInputPort($outPortArrayA)->addOutputPort($inPortA));

    $dataOutNodeA = new PortNode((new CustomBox)->setName("out")->addInputPort($inPortA));

    // make connections in A tree
    $dataInNodeA->addChild($outPortA->getName(), $execNodeA);
    $execNodeA->addParent($outPortA->getName(), $dataInNodeA);
    $dataInNodeArrayA->addChild($outPortArrayA->getName(), $execNodeA);
    $execNodeA->addParent($outPortArrayA->getName(), $dataInNodeArrayA);
    $preExecNodeA->addChild($preExecPortA->getName(), $execNodeA);
    $execNodeA->addParent($preExecPortA->getName(), $preExecNodeA);
    $execNodeA->addChild($inPortA->getName(), $dataOutNodeA);
    $dataOutNodeA->addParent($inPortA->getName(), $execNodeA);

    $treeA = new MergeTree();
    $treeA->addInputNode($dataInNodeA);
    $treeA->addInputNode($dataInNodeArrayA);
    $treeA->addOtherNode($execNodeA);
    $treeA->addOtherNode($preExecNodeA);
    $treeA->addOutputNode($dataOutNodeA);

    //
    // Tree B - pipeline: inA -> exec -> outA; inB -> exec -> outB
    //

    $testInputVarBA = new Variable((new VariableMeta)->setName("test-ba-input")->setType("file")->setValue("infile"));
    $testInputVarBB = new Variable((new VariableMeta)->setName("test-bb-input")->setType("file")->setValue(""));
    $testOutputVarBA = new Variable((new VariableMeta)->setName("test-ba-output")->setType("file")->setValue(""));
    $testOutputVarBB = new Variable((new VariableMeta)->setName("test-bb-output")->setType("file")->setValue(""));

    $this->envVarTableB = (new VariablesTable)->set($testInputVarBA);
    $this->exerVarTableB = (new VariablesTable)->set($testInputVarBB);
    $this->pipeVarTableB = (new VariablesTable)->set($testInputVarBA)->set($testInputVarBB)->set($testOutputVarBA)->set($testOutputVarBB);

    $outPortBA = new Port((new PortMeta)->setName("data-in-a")->setType(VariableTypes::$FILE_TYPE)->setVariable($testInputVarBA->getName()));
    $dataInNodeBA = new PortNode((new CustomBox)->setName("inBA")->addOutputPort($outPortBA));

    $outPortBB = new Port((new PortMeta)->setName("data-in-b")->setType(VariableTypes::$FILE_TYPE)->setVariable($testInputVarBB->getName()));
    $dataInNodeBB = new PortNode((new CustomBox)->setName("inBB")->addOutputPort($outPortBB));

    $inPortBA = new Port((new PortMeta)->setName("data-out-a")->setType(VariableTypes::$FILE_TYPE)->setVariable($testOutputVarBA->getName()));
    $inPortBB = new Port((new PortMeta)->setName("data-out-b")->setType(VariableTypes::$FILE_TYPE)->setVariable($testOutputVarBB->getName()));
    $execNodeB = new PortNode((new CustomBox)->setName("execB")->addInputPort($outPortBA)
      ->addInputPort($outPortBB)->addOutputPort($inPortBA)->addOutputPort($inPortBB));

    $dataOutNodeBA = new PortNode((new CustomBox)->setName("outBA")->addInputPort($inPortBA));
    $dataOutNodeBB = new PortNode((new CustomBox)->setName("outBB")->addInputPort($inPortBB));

    // make connections in B tree
    $dataInNodeBA->addChild($outPortBA->getName(), $execNodeB);
    $execNodeB->addParent($outPortBA->getName(), $dataInNodeBA);
    $dataInNodeBB->addChild($outPortBB->getName(), $execNodeB);
    $execNodeB->addParent($outPortBB->getName(), $dataInNodeBB);
    $execNodeB->addChild($inPortBA->getName(), $dataOutNodeBA);
    $dataOutNodeBA->addParent($inPortBA->getName(), $execNodeB);
    $execNodeB->addChild($inPortBB->getName(), $dataOutNodeBB);
    $dataOutNodeBB->addParent($inPortBB->getName(), $execNodeB);

    $treeB = new MergeTree();
    $treeB->addInputNode($dataInNodeBA);
    $treeB->addInputNode($dataInNodeBB);
    $treeB->addOtherNode($execNodeB);
    $treeB->addOutputNode($dataOutNodeBA);
    $treeB->addOutputNode($dataOutNodeBB);

    //
    $this->treeArray = array();
    $this->treeArray[] = $treeA;
    $this->treeArray[] = $treeB;
  }


  public function testMissingVariableInInputBoxTables() {
    Assert::exception(function () {
      $this->pipeVarTableB->remove("test-bb-input");
      $this->resolver->resolve($this->treeArray[1], $this->envVarTableB, $this->exerVarTableB, $this->pipeVarTableB, []);
    }, ExerciseConfigException::class);
  }

  public function testMissingReferencedVariable() {
    Assert::exception(function () {
      $this->exerVarTableA->remove("test-a-reference-variable");
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, []);
    }, ExerciseConfigException::class);
  }

  public function testVariableNamesNotMatchesInInputBox() {
    Assert::exception(function () {
      $newPort = new Port((new PortMeta)->setName("data-in")->setType(VariableTypes::$FILE_TYPE)->setVariable("something which does not exist"));
      $box = current($this->treeArray[0]->getInputNodes())->getBox();
      $box->clearOutputPorts()->addOutputPort($newPort);
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, []);
    }, ExerciseConfigException::class);
  }

  public function testVariableNamesNotMatchesInOtherBox() {
    Assert::exception(function () {
      $newPort = new Port((new PortMeta)->setName("data-out")->setType(VariableTypes::$FILE_TYPE)->setVariable("something which does not exist"));
      $box = current($this->treeArray[0]->getOtherNodes())->getBox();
      $box->clearOutputPorts()->addOutputPort($newPort);
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, []);
    }, ExerciseConfigException::class);
  }

  public function testMissingVariableInPipelinesTable() {
    Assert::exception(function () {
      $this->pipeVarTableA->remove("test-a-pre-exec");
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, []);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenInputNodesParentNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOtherNodes()[0]->clearParents();
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, []);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenNodesParentNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOutputNodes()[0]->clearParents();
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, []);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenNodesChildNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOtherNodes()[1]->clearChildren();
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, []);
    }, ExerciseConfigException::class);
  }

  public function testNotMatchingRegexp() {
    Assert::throws(function () {
      $files = ["infile"];
      $this->envVarTableA->set(new Variable((new VariableMeta)->setName("test-a-input")->setType("file")->setValue("out*")));
      $this->resolver->resolve($this->treeArray[0], $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, $files);
    }, ExerciseConfigException::class);
  }

  public function testRegexp() {
    $tree = $this->treeArray[0];
    $port = $tree->getInputNodes()[0]->getBox()->getOutputPorts()["data-in"];
    $portArray = $tree->getInputNodes()[1]->getBox()->getOutputPorts()["data-in-arr"];

    $files = ["infile"];
    $this->envVarTableA->set(new Variable((new VariableMeta)->setName("test-a-input")->setType("file")->setValue("in*")));
    $this->resolver->resolve($tree, $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, $files);
    Assert::equal("infile", $port->getVariableValue()->getValue());

    $files = ["infile", "invar"];
    $this->envVarTableA->set(new Variable((new VariableMeta)->setName("test-a-input")->setType("file")->setValue("in*")));
    $this->resolver->resolve($tree, $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, $files);
    Assert::equal("infile", $port->getVariableValue()->getValue());

    $files = ["infile", "invar"];
    $this->envVarTableA->set(new Variable((new VariableMeta)->setName("test-a-input-array")->setType("file[]")->setValue("in*")));
    $this->resolver->resolve($tree, $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, $files);
    Assert::equal($files, $portArray->getVariableValue()->getValue());

    $files = ["infile", "outvar"];
    $this->envVarTableA->set(new Variable((new VariableMeta)->setName("test-a-input-array")->setType("file[]")->setValue("in*")));
    $this->resolver->resolve($tree, $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, $files);
    Assert::equal(["infile"], $portArray->getVariableValue()->getValue());
  }

  public function testCorrect() {
    $trees = $this->treeArray;
    $treeA = $trees[0];
    $treeB = $trees[1];

    $files = ["infile"];
    $this->resolver->resolve($treeA, $this->envVarTableA, $this->exerVarTableA, $this->pipeVarTableA, $files);
    $this->resolver->resolve($treeB, $this->envVarTableB, $this->exerVarTableB, $this->pipeVarTableB, $files);

    // Tree A
    Assert::equal("test-a-input", $treeA->getInputNodes()[0]->getBox()->getOutputPorts()["data-in"]->getVariableValue()->getName());
    Assert::equal("test-a-input", $treeA->getOtherNodes()[0]->getBox()->getInputPorts()["data-in"]->getVariableValue()->getName());
    Assert::equal("test-a-reference-variable", $treeA->getOtherNodes()[0]->getBox()->getInputPorts()["pre-data"]->getVariableValue()->getName());
    Assert::equal("test-a-reference-variable", $treeA->getOtherNodes()[1]->getBox()->getOutputPorts()["pre-data"]->getVariableValue()->getName());
    Assert::equal("test-a-output-reference", $treeA->getOtherNodes()[0]->getBox()->getOutputPorts()["data-out"]->getVariableValue()->getName());
    Assert::equal("test-a-output-reference", $treeA->getOutputNodes()[0]->getBox()->getInputPorts()["data-out"]->getVariableValue()->getName());

    // Tree B
    Assert::equal("test-ba-input", $treeB->getInputNodes()[0]->getBox()->getOutputPorts()["data-in-a"]->getVariableValue()->getName());
    Assert::equal("test-ba-input", $treeB->getOtherNodes()[0]->getBox()->getInputPorts()["data-in-a"]->getVariableValue()->getName());
    Assert::equal("test-bb-input", $treeB->getInputNodes()[1]->getBox()->getOutputPorts()["data-in-b"]->getVariableValue()->getName());
    Assert::equal("test-bb-input", $treeB->getOtherNodes()[0]->getBox()->getInputPorts()["data-in-b"]->getVariableValue()->getName());
    Assert::equal("test-ba-output", $treeB->getOtherNodes()[0]->getBox()->getOutputPorts()["data-out-a"]->getVariableValue()->getName());
    Assert::equal("test-ba-output", $treeB->getOutputNodes()[0]->getBox()->getInputPorts()["data-out-a"]->getVariableValue()->getName());
    Assert::equal("test-bb-output", $treeB->getOtherNodes()[0]->getBox()->getOutputPorts()["data-out-b"]->getVariableValue()->getName());
    Assert::equal("test-bb-output", $treeB->getOutputNodes()[1]->getBox()->getInputPorts()["data-out-b"]->getVariableValue()->getName());
  }

}

# Testing methods run
$testCase = new TestVariablesResolver();
$testCase->run();
