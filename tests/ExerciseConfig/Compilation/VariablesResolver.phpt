<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationContext;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Pipeline\Box\CustomBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
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

  private static $exerciseFiles = [
    "input.A.name" => "input.A.hash",
    "input.BA.name" => "input.BA.hash"
  ];
  private static $pipelineFiles = [
    "input.BC.name" => "input.BC.hash"
  ];


  public function __construct() {
    $this->resolver = new VariablesResolver();
  }

  protected function setUp() {

    //
    // Tree A -> pipeline: in -> exec -> out; pre-exec -> exec
    //

    $referencedVarA = (new Variable("file"))->setName("test-a-reference-variable")->setValue("booya");
    $testInputVarA = (new Variable("file"))->setName("test-a-input")->setValue("infile");
    $testRemoteInputVarA = (new Variable("remote-file"))->setName("test-a-input")->setValue("input.A.name");
    $testInputArrayVarA = (new Variable("file[]"))->setName("test-a-input-array")->setValue("in*");
    $preExecVarA = (new Variable("file"))->setName("test-a-pre-exec")->setValue('$test-a-reference-variable');
    $outputReferencedVarA = (new Variable("file"))->setName("test-a-output-reference")->setValue("yaboo");
    $testOutputVarA = (new Variable("file"))->setName("test-a-output")->setValue('$test-a-output-reference');

    $this->envVarTableA = (new VariablesTable)->set($outputReferencedVarA)->set($testRemoteInputVarA)->set($testInputArrayVarA);
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
    // Tree B - pipeline: inA -> exec -> outA; inB -> exec -> outB; inC -> exec
    //

    $testInputVarBA = (new Variable("file[]"))->setName("test-ba-input")->setValue(["infile"]);
    $testRemoteInputVarBA = (new Variable("remote-file[]"))->setName("test-ba-input")->setValue(["input.BA.name"]);
    $testInputVarBB = (new Variable("file"))->setName("test-bb-input")->setValue("");
    $testOutputVarBA = (new Variable("file"))->setName("test-ba-output")->setValue("");
    $testOutputVarBB = (new Variable("file"))->setName("test-bb-output")->setValue("");
    $testOnlyInputVarB = (new Variable("string"))->setName("test-b-only-input")->setValue("only-input");
    $testInputVarBC = (new Variable("file"))->setName("test-bc-input")->setValue("input.BC");
    $testRemoteInputVarBC = (new Variable("remote-file"))->setName("test-bc-remote-input")->setValue("input.BC.name");

    $this->envVarTableB = (new VariablesTable)->set($testRemoteInputVarBA);
    $this->exerVarTableB = (new VariablesTable)->set($testInputVarBB);
    $this->pipeVarTableB = (new VariablesTable)->set($testInputVarBA)
      ->set($testInputVarBB)->set($testOutputVarBA)->set($testOutputVarBB)
      ->set($testOnlyInputVarB)->set($testInputVarBC)->set($testRemoteInputVarBC);

    $outPortBA = new Port((new PortMeta)->setName("data-in-a")->setType(VariableTypes::$FILE_TYPE)->setVariable($testInputVarBA->getName()));
    $dataInNodeBA = new PortNode((new CustomBox)->setName("inBA")->addOutputPort($outPortBA));

    $outPortBB = new Port((new PortMeta)->setName("data-in-b")->setType(VariableTypes::$FILE_TYPE)->setVariable($testInputVarBB->getName()));
    $dataInNodeBB = new PortNode((new CustomBox)->setName("inBB")->addOutputPort($outPortBB));

    $inPortBC = new Port((new PortMeta)->setName("data-in-remote-c")->setType(VariableTypes::$REMOTE_FILE_TYPE)->setVariable($testRemoteInputVarBC->getName()));
    $outPortBC = new Port((new PortMeta)->setName("data-in-c")->setType(VariableTypes::$FILE_TYPE)->setVariable($testInputVarBC->getName()));
    $dataInNodeBC = new PortNode((new CustomBox)->setName("inBC")->addInputPort($inPortBC)->addOutputPort($outPortBC));

    $onlyInPortB = new Port((new PortMeta)->setName("data-only-in")->setType(VariableTypes::$STRING_TYPE)->setVariable($testOnlyInputVarB->getName()));
    $inPortBA = new Port((new PortMeta)->setName("data-out-a")->setType(VariableTypes::$FILE_TYPE)->setVariable($testOutputVarBA->getName()));
    $inPortBB = new Port((new PortMeta)->setName("data-out-b")->setType(VariableTypes::$FILE_TYPE)->setVariable($testOutputVarBB->getName()));
    $execNodeB = new PortNode((new CustomBox)->setName("execB")
      ->addInputPort($onlyInPortB)->addInputPort($outPortBA)
      ->addInputPort($outPortBB)->addOutputPort($inPortBA)
      ->addOutputPort($inPortBB)->addInputPort($outPortBC));

    $dataOutNodeBA = new PortNode((new CustomBox)->setName("outBA")->addInputPort($inPortBA));
    $dataOutNodeBB = new PortNode((new CustomBox)->setName("outBB")->addInputPort($inPortBB));

    // make connections in B tree
    $dataInNodeBA->addChild($outPortBA->getName(), $execNodeB);
    $execNodeB->addParent($outPortBA->getName(), $dataInNodeBA);
    $dataInNodeBB->addChild($outPortBB->getName(), $execNodeB);
    $execNodeB->addParent($outPortBB->getName(), $dataInNodeBB);
    $dataInNodeBC->addChild($outPortBC->getName(), $execNodeB);
    $execNodeB->addParent($outPortBC->getName(), $dataInNodeBC);
    $execNodeB->addChild($inPortBA->getName(), $dataOutNodeBA);
    $dataOutNodeBA->addParent($inPortBA->getName(), $execNodeB);
    $execNodeB->addChild($inPortBB->getName(), $dataOutNodeBB);
    $dataOutNodeBB->addParent($inPortBB->getName(), $execNodeB);

    $treeB = new MergeTree();
    $treeB->addInputNode($dataInNodeBA);
    $treeB->addInputNode($dataInNodeBB);
    $treeB->addOtherNode($execNodeB);
    $treeB->addOtherNode($dataInNodeBC);
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
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableB, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[1], $this->exerVarTableB, $this->pipeVarTableB, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testMissingReferencedVariable() {
    Assert::exception(function () {
      $this->exerVarTableA->remove("test-a-reference-variable");
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testVariableNamesNotMatchesInInputBox() {
    Assert::exception(function () {
      $newPort = new Port((new PortMeta)->setName("data-in")->setType(VariableTypes::$FILE_TYPE)->setVariable("something which does not exist"));
      $box = current($this->treeArray[0]->getInputNodes())->getBox();
      $box->clearOutputPorts()->addOutputPort($newPort);

      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testVariableNamesNotMatchesInOtherBox() {
    Assert::exception(function () {
      $newPort = new Port((new PortMeta)->setName("data-out")->setType(VariableTypes::$FILE_TYPE)->setVariable("something which does not exist"));
      $box = current($this->treeArray[0]->getOtherNodes())->getBox();
      $box->clearOutputPorts()->addOutputPort($newPort);

      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testMissingVariableInPipelinesTable() {
    Assert::exception(function () {
      $this->pipeVarTableA->remove("test-a-pre-exec");
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenInputNodesParentNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOtherNodes()[0]->clearParents();
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenNodesParentNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOutputNodes()[0]->clearParents();
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testBadConnectionBetweenNodesChildNotFound() {
    Assert::exception(function () {
      $this->treeArray[0]->getOtherNodes()[1]->clearChildren();
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testUnknownHashOfFile() {
    Assert::exception(function () {
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], [], [], "");
      $params = CompilationParams::create();
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, [], $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testNotMatchingRegexp() {
    Assert::throws(function () {
      $files = ["infile"];
      $this->envVarTableA->set((new Variable("file"))->setName("test-a-input")->setValue("out*"));
      $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
      $params = CompilationParams::create($files);
      $this->resolver->resolve($this->treeArray[0], $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    }, ExerciseConfigException::class);
  }

  public function testRegexp() {
    $tree = $this->treeArray[0];
    $box = $tree->getInputNodes()[0]->getBox();
    $boxArray = $tree->getInputNodes()[1]->getBox();
    $context = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");

    $files = ["infile"];
    $params = CompilationParams::create($files);
    $this->envVarTableA->set((new Variable("file"))->setName("test-a-input")->setValue("in*"));
    $this->resolver->resolve($tree, $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    Assert::equal("infile", $box->getInputVariable()->getValue());

    $files = ["infile", "invar"];
    $params = CompilationParams::create($files);
    $this->envVarTableA->set((new Variable("file"))->setName("test-a-input")->setValue("in*"));
    $this->resolver->resolve($tree, $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    Assert::equal("infile", $box->getInputVariable()->getValue());

    $files = ["infile", "invar"];
    $params = CompilationParams::create($files);
    $this->envVarTableA->set((new Variable("file[]"))->setName("test-a-input-array")->setValue("in*"));
    $this->resolver->resolve($tree, $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    Assert::equal($files, $boxArray->getInputVariable()->getValue());

    $files = ["infile", "outvar"];
    $params = CompilationParams::create($files);
    $this->envVarTableA->set((new Variable("file[]"))->setName("test-a-input-array")->setValue("in*"));
    $this->resolver->resolve($tree, $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $context, $params);
    Assert::equal(["infile"], $boxArray->getInputVariable()->getValue());
  }

  public function testCorrect() {
    $trees = $this->treeArray;
    $treeA = $trees[0];
    $treeB = $trees[1];

    $files = ["infile"];
    $params = CompilationParams::create($files);
    $contextA = CompilationContext::create(new ExerciseConfig(), $this->envVarTableA, [], self::$exerciseFiles, [], "");
    $contextB = CompilationContext::create(new ExerciseConfig(), $this->envVarTableB, [], self::$exerciseFiles, [], "");

    $this->resolver->resolve($treeA, $this->exerVarTableA, $this->pipeVarTableA, self::$pipelineFiles, $contextA, $params);
    $this->resolver->resolve($treeB, $this->exerVarTableB, $this->pipeVarTableB, self::$pipelineFiles, $contextB, $params);


    //**************************************************************************
    // Tree A
    Assert::equal("test-a-input", $treeA->getInputNodes()[0]->getBox()->getInputVariable()->getName());
    Assert::equal("input.A.hash", $treeA->getInputNodes()[0]->getBox()->getInputVariable()->getValue());

    Assert::equal("test-a-input", $treeA->getInputNodes()[0]->getBox()->getOutputPorts()["data-in"]->getVariableValue()->getName());
    Assert::equal("test-a-input", $treeA->getOtherNodes()[0]->getBox()->getInputPorts()["data-in"]->getVariableValue()->getName());

    Assert::equal("test-a-reference-variable", $treeA->getOtherNodes()[0]->getBox()->getInputPorts()["pre-data"]->getVariableValue()->getName());
    Assert::equal("test-a-reference-variable", $treeA->getOtherNodes()[1]->getBox()->getOutputPorts()["pre-data"]->getVariableValue()->getName());

    Assert::equal("test-a-output-reference", $treeA->getOtherNodes()[0]->getBox()->getOutputPorts()["data-out"]->getVariableValue()->getName());
    Assert::equal("test-a-output-reference", $treeA->getOutputNodes()[0]->getBox()->getInputPorts()["data-out"]->getVariableValue()->getName());


    //**************************************************************************
    // Tree B
    Assert::equal("test-ba-input", $treeB->getInputNodes()[0]->getBox()->getInputVariable()->getName());
    Assert::equal(["input.BA.hash"], $treeB->getInputNodes()[0]->getBox()->getInputVariable()->getValue());

    Assert::equal("test-ba-input", $treeB->getInputNodes()[0]->getBox()->getOutputPorts()["data-in-a"]->getVariableValue()->getName());
    Assert::equal("test-ba-input", $treeB->getOtherNodes()[0]->getBox()->getInputPorts()["data-in-a"]->getVariableValue()->getName());

    Assert::equal("test-bb-input", $treeB->getInputNodes()[1]->getBox()->getOutputPorts()["data-in-b"]->getVariableValue()->getName());
    Assert::equal("test-b-only-input", $treeB->getOtherNodes()[0]->getBox()->getInputPorts()["data-only-in"]->getVariableValue()->getName());
    Assert::equal("test-bb-input", $treeB->getOtherNodes()[0]->getBox()->getInputPorts()["data-in-b"]->getVariableValue()->getName());

    Assert::equal("test-ba-output", $treeB->getOtherNodes()[0]->getBox()->getOutputPorts()["data-out-a"]->getVariableValue()->getName());
    Assert::equal("test-ba-output", $treeB->getOutputNodes()[0]->getBox()->getInputPorts()["data-out-a"]->getVariableValue()->getName());

    Assert::equal("test-bb-output", $treeB->getOtherNodes()[0]->getBox()->getOutputPorts()["data-out-b"]->getVariableValue()->getName());
    Assert::equal("test-bb-output", $treeB->getOutputNodes()[1]->getBox()->getInputPorts()["data-out-b"]->getVariableValue()->getName());

    Assert::equal("test-bc-remote-input", $treeB->getOtherNodes()[1]->getBox()->getInputPort("data-in-remote-c")->getVariableValue()->getName());
    Assert::equal("input.BC.hash", $treeB->getOtherNodes()[1]->getBox()->getInputPort("data-in-remote-c")->getVariableValue()->getValue());
    Assert::equal("input.BC", $treeB->getOtherNodes()[1]->getBox()->getOutputPort("data-in-c")->getVariableValue()->getValue());
  }

}

# Testing methods run
$testCase = new TestVariablesResolver();
$testCase->run();
