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
    // Tree A -> simple pipeline: in -> exec -> out
    //

    $testInputVarA = new FileVariable((new VariableMeta)->setName("test-a-input"));
    $testOutputVarA = new FileVariable((new VariableMeta)->setName("test-a-output"));

    $envVarTableA = (new VariablesTable)->set($testInputVarA);
    $exerVarTableA = (new VariablesTable);
    $pipeVarTableA = (new VariablesTable)->set($testOutputVarA);

    $outPortA = new FilePort((new PortMeta)->setName("data-in")->setVariable($testInputVarA->getName()));
    $dataInNodeA = new Node((new CustomBox)->addOutputPort($outPortA));
    $dataInNodeA->setEnvironmentConfigVariables($envVarTableA)
      ->setExerciseConfigVariables($exerVarTableA)
      ->setPipelineVariables($pipeVarTableA);

    $inPortA = new FilePort((new PortMeta)->setName("data-out")->setVariable($testOutputVarA->getName()));
    $execNodeA = new Node((new CustomBox)->addInputPort($outPortA)->addOutputPort($inPortA));
    $execNodeA->setEnvironmentConfigVariables($envVarTableA)
      ->setExerciseConfigVariables($exerVarTableA)
      ->setPipelineVariables($pipeVarTableA);

    $dataOutNodeA = new Node((new CustomBox)->addInputPort($inPortA));
    $dataOutNodeA->setEnvironmentConfigVariables($envVarTableA)
      ->setExerciseConfigVariables($exerVarTableA)
      ->setPipelineVariables($pipeVarTableA);

    // make connections in A tree
    $dataInNodeA->addChild($outPortA->getName(), $execNodeA);
    $execNodeA->addParent($outPortA->getName(), $dataInNodeA);
    $execNodeA->addChild($inPortA->getName(), $dataOutNodeA);
    $dataOutNodeA->addParent($inPortA->getName(), $execNodeA);

    $treeA = new MergeTree();
    $treeA->addInputNode($dataInNodeA);
    $treeA->addOtherNode($execNodeA);
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

    $outPortBA = new FilePort((new PortMeta)->setName("data-in")->setVariable($testInputVarBA->getName()));
    $dataInNodeBA = new Node((new CustomBox)->addOutputPort($outPortBA));
    $dataInNodeBA->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $outPortBB = new FilePort((new PortMeta)->setName("data-in")->setVariable($testInputVarBB->getName()));
    $dataInNodeBB = new Node((new CustomBox)->addOutputPort($outPortBB));
    $dataInNodeBB->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $inPortBA = new FilePort((new PortMeta)->setName("data-out")->setVariable($testOutputVarBA->getName()));
    $inPortBB = new FilePort((new PortMeta)->setName("data-out")->setVariable($testOutputVarBB->getName()));
    $execNodeB = new Node((new CustomBox)->addInputPort($outPortBA)
      ->addInputPort($outPortBB)->addOutputPort($inPortBA)->addOutputPort($inPortBB));
    $execNodeB->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $dataOutNodeBA = new Node((new CustomBox)->addInputPort($inPortBA));
    $dataOutNodeBA->setEnvironmentConfigVariables($envVarTableB)
      ->setExerciseConfigVariables($exerVarTableB)
      ->setPipelineVariables($pipeVarTableB);

    $dataOutNodeBB = new Node((new CustomBox)->addInputPort($inPortBB));
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

  public function testVariableNamesNotMatches() {
    // @todo
  }

  public function testMissingVariableInPipelinesTable() {
    // @todo
  }

  public function testBadConnectionBetweenNodes() {
    // @todo: test two cases... child cannot be found in parent ... parent cannot be found in child
  }

  public function testJoinNode() {
    // @todo
  }

  public function testCorrectExerciseTable() {
    // @todo
  }

  public function testCorrectEnvironmentTable() {
    // @todo
  }

  public function testCorrectPipelinesTable() {
    // @todo
  }

  public function testCorrect() {
    Assert::noError(function () {
      $this->resolver->resolve($this->treeArray);
    });

    // check for values
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestVariablesResolver();
$testCase->run();
