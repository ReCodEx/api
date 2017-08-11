<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\FileVariable;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\FilePort;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableMeta;
use App\Helpers\ExerciseConfig\VariablesTable;
use Tester\Assert;


class TestVariablesResolver extends Tester\TestCase
{
  /** @var VariablesResolver */
  private $resolver;

  /** @var MergeTree[] */
  private $treeArray = array();

  public function __construct() {
    $this->resolver = new VariablesResolver();
  }

  protected function setUp() {

    //
    // Tree A -> pipeline: in -> exec -> out; pre-exec -> exec
    //

    $testInputVarA = new FileVariable((new VariableMeta)->setName("test-a-input"));
    $preExecVarA = new FileVariable((new VariableMeta)->setName("test-a-pre-exec"));
    $testOutputVarA = new FileVariable((new VariableMeta)->setName("test-a-output"));

    $envVarTableA = (new VariablesTable)->set($testInputVarA);
    $exerVarTableA = (new VariablesTable);
    $pipeVarTableA = (new VariablesTable)->set($testOutputVarA)->set($preExecVarA);

    $outPortA = new FilePort((new PortMeta)->setName("data-in")->setVariable($testInputVarA->getName()));
    $dataInNodeA = new Node((new CustomBox)->setName("in")->addOutputPort($outPortA));
    $dataInNodeA->setEnvironmentConfigVariables($envVarTableA)
      ->setExerciseConfigVariables($exerVarTableA)
      ->setPipelineVariables($pipeVarTableA);

    $preExecPortA = new FilePort((new PortMeta)->setName("pre-data")->setVariable($preExecVarA->getName()));
    $preExecNodeA = new Node((new CustomBox)->setName("pre-exec")->addOutputPort($preExecPortA));
    $preExecNodeA->setEnvironmentConfigVariables($envVarTableA)
      ->setExerciseConfigVariables($exerVarTableA)
      ->setPipelineVariables($pipeVarTableA);

    $inPortA = new FilePort((new PortMeta)->setName("data-out")->setVariable($testOutputVarA->getName()));
    $execNodeA = new Node((new CustomBox)->setName("exec")
      ->addInputPort($preExecPortA)->addInputPort($outPortA)->addOutputPort($inPortA));
    $execNodeA->setEnvironmentConfigVariables($envVarTableA)
      ->setExerciseConfigVariables($exerVarTableA)
      ->setPipelineVariables($pipeVarTableA);

    $dataOutNodeA = new Node((new CustomBox)->setName("out")->addInputPort($inPortA));
    $dataOutNodeA->setEnvironmentConfigVariables($envVarTableA)
      ->setExerciseConfigVariables($exerVarTableA)
      ->setPipelineVariables($pipeVarTableA);

    // make connections in A tree
    $dataInNodeA->addChild($outPortA->getName(), $execNodeA);
    $execNodeA->addParent($outPortA->getName(), $dataInNodeA);
    $preExecNodeA->addChild($preExecPortA->getName(), $execNodeA);
    $execNodeA->addParent($preExecPortA->getName(), $preExecNodeA);
    $execNodeA->addChild($inPortA->getName(), $dataOutNodeA);
    $dataOutNodeA->addParent($inPortA->getName(), $execNodeA);

    $treeA = new MergeTree();
    $treeA->addInputNode($dataInNodeA);
    $treeA->addOtherNode($execNodeA);
    $treeA->addOtherNode($preExecNodeA);
    $treeA->addOutputNode($dataOutNodeA);

    //
    // Tree B - pipeline: inA -> exec -> outA; inB -> exec -> outB
    //

    $testInputVarBA = new FileVariable((new VariableMeta)->setName("test-ba-input"));
    $testInputVarBB = new FileVariable((new VariableMeta)->setName("test-bb-input"));
    $testOutputVarBA = new FileVariable((new VariableMeta)->setName("test-ba-output"));
    $testOutputVarBB = new FileVariable((new VariableMeta)->setName("test-bb-output"));

    $envVarTableB = (new VariablesTable)->set($testInputVarBA);
    $exerVarTableB = (new VariablesTable)->set($testInputVarBB);
    $pipeVarTableB = (new VariablesTable)->set($testOutputVarBA)->set($testOutputVarBB);

    $outPortBA = new FilePort((new PortMeta)->setName("data-in-a")->setVariable($testInputVarBA->getName()));
    $dataInNodeBA = new Node((new CustomBox)->setName("inBA")->addOutputPort($outPortBA));
    $dataInNodeBA->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $outPortBB = new FilePort((new PortMeta)->setName("data-in-b")->setVariable($testInputVarBB->getName()));
    $dataInNodeBB = new Node((new CustomBox)->setName("inBB")->addOutputPort($outPortBB));
    $dataInNodeBB->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $inPortBA = new FilePort((new PortMeta)->setName("data-out-a")->setVariable($testOutputVarBA->getName()));
    $inPortBB = new FilePort((new PortMeta)->setName("data-out-b")->setVariable($testOutputVarBB->getName()));
    $execNodeB = new Node((new CustomBox)->setName("execB")->addInputPort($outPortBA)
      ->addInputPort($outPortBB)->addOutputPort($inPortBA)->addOutputPort($inPortBB));
    $execNodeB->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $dataOutNodeBA = new Node((new CustomBox)->setName("outBA")->addInputPort($inPortBA));
    $dataOutNodeBA->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $dataOutNodeBB = new Node((new CustomBox)->setName("outBB")->addInputPort($inPortBB));
    $dataOutNodeBB->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

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
      current($this->treeArray[0]->getInputNodes())->setEnvironmentConfigVariables(new VariablesTable());
      current($this->treeArray[0]->getInputNodes())->setExerciseConfigVariables(new VariablesTable());
      $this->resolver->resolve($this->treeArray);
    }, ExerciseConfigException::class);
  }

  public function testVariableNamesNotMatchesInInputBox() {
    Assert::exception(function () {
      $newPort = new FilePort((new PortMeta)->setName("data-in")->setVariable("something which does not exist"));
      $box = current($this->treeArray[0]->getInputNodes())->getBox();
      $box->clearOutputPorts()->addOutputPort($newPort);
      $this->resolver->resolve($this->treeArray);
    }, ExerciseConfigException::class);
  }

  public function testVariableNamesNotMatchesInOtherBox() {
    Assert::exception(function () {
      $newPort = new FilePort((new PortMeta)->setName("data-out")->setVariable("something which does not exist"));
      $box = current($this->treeArray[0]->getOtherNodes())->getBox();
      $box->clearOutputPorts()->addOutputPort($newPort);
      $this->resolver->resolve($this->treeArray);
    }, ExerciseConfigException::class);
  }

  public function testMissingVariableInPipelinesTable() {
    Assert::exception(function () {
      current($this->treeArray[0]->getOtherNodes())->setPipelineVariables(new VariablesTable());
      current($this->treeArray[0]->getOutputNodes())->setPipelineVariables(new VariablesTable());
      $this->resolver->resolve($this->treeArray);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenInputNodesParentNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOtherNodes()[0]->clearParents();
      $this->resolver->resolve($this->treeArray);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenNodesParentNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOutputNodes()[0]->clearParents();
      $this->resolver->resolve($this->treeArray);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenNodesChildNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOtherNodes()[1]->clearChildren();
      $this->resolver->resolve($this->treeArray);
    }, ExerciseConfigException::class);
  }

  public function testCorrect() {
    $trees = $this->treeArray;
    $this->resolver->resolve($trees);

    $treeA = $trees[0];
    $treeB = $trees[1];

    // Tree A
    Assert::equal("test-a-input", $treeA->getInputNodes()[0]->getBox()->getOutputPorts()["data-in"]->getVariableValue()->getName());
    Assert::equal("test-a-input", $treeA->getOtherNodes()[0]->getBox()->getInputPorts()["data-in"]->getVariableValue()->getName());
    Assert::equal("test-a-pre-exec", $treeA->getOtherNodes()[0]->getBox()->getInputPorts()["pre-data"]->getVariableValue()->getName());
    Assert::equal("test-a-pre-exec", $treeA->getOtherNodes()[1]->getBox()->getOutputPorts()["pre-data"]->getVariableValue()->getName());
    Assert::equal("test-a-output", $treeA->getOtherNodes()[0]->getBox()->getOutputPorts()["data-out"]->getVariableValue()->getName());
    Assert::equal("test-a-output", $treeA->getOutputNodes()[0]->getBox()->getInputPorts()["data-out"]->getVariableValue()->getName());

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
