<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataOutBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\GccCompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JudgeNormalBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\FilePort;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Validation\PipelineValidator;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableMeta;
use Tester\Assert;


/**
 * @testCase
 */
class TestPipelineValidator extends Tester\TestCase
{
  /** @var PipelineValidator */
  private $validator;

  protected function setUp() {
    $this->validator = new PipelineValidator();
  }

  public function testCorrect() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("input", "file", "")));
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("output", "file", "")));

    $dataInBoxMeta = new BoxMeta();
    $dataInBoxMeta->setName("input");
    $dataInBoxMeta->addOutputPort(new FilePort(PortMeta::create(DataInBox::$DATA_IN_PORT_KEY, "file", "input")));
    $pipeline->set(new DataInBox($dataInBoxMeta));

    $compileBoxMeta = new BoxMeta();
    $compileBoxMeta->setName("compile");
    $compileBoxMeta->addInputPort(new FilePort(PortMeta::create(GccCompilationBox::$SOURCE_FILE_PORT_KEY, "file",
      "input")));
    $compileBoxMeta->addOutputPort(new FilePort(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file",
      "output")));
    $pipeline->set(new GccCompilationBox($compileBoxMeta));

    $dataOutBoxMeta = new BoxMeta();
    $dataOutBoxMeta->setName("output");
    $dataOutBoxMeta->addInputPort(new FilePort(PortMeta::create(DataOutBox::$DATA_OUT_PORT_KEY, "file", "output")));
    $pipeline->set(new DataOutBox($dataOutBoxMeta));

    Assert::noError(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    });
  }

  public function testEmpty() {
    $pipeline = new Pipeline();

    Assert::noError(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    });
  }

  public function testUnusedVariable() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("varA", "file", "a.txt")));
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("varB", "file", "b.txt")));

    $varAInMeta = new BoxMeta();
    $varAInMeta->setName("varA_in");
    $varAInMeta->addOutputPort(new FilePort(PortMeta::create("varA_in", "file", "varA")));
    $pipeline->set(new DataInBox($varAInMeta));

    $varBInMeta = new BoxMeta();
    $varBInMeta->setName("varB_in");
    $varBInMeta->addOutputPort(new FilePort(PortMeta::create("varB_in", "file", "varB")));
    $pipeline->set(new DataInBox($varBInMeta));

    $judgeBoxMeta = new BoxMeta();
    $pipeline->set(new JudgeNormalBox($judgeBoxMeta));
    $judgeBoxMeta->setName("judge");
    $judgeBoxMeta->addInputPort(new FilePort(PortMeta::create(JudgeNormalBox::$ACTUAL_OUTPUT_PORT_KEY, "file", "varA")));
    $judgeBoxMeta->addInputPort(new FilePort(PortMeta::create(JudgeNormalBox::$EXPECTED_OUTPUT_PORT_KEY, "file", "varA")));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#(No port uses variable|Unused variable)#i');
  }

  public function testUndefinedVariable() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("varA", "file", "a.txt")));
    $judgeBoxMeta = new BoxMeta();
    $pipeline->set(new JudgeNormalBox($judgeBoxMeta));
    $judgeBoxMeta->setName("judge");
    $judgeBoxMeta->addInputPort(new FilePort(PortMeta::create(JudgeNormalBox::$ACTUAL_OUTPUT_PORT_KEY, "file", "varA")));
    $judgeBoxMeta->addInputPort(new FilePort(PortMeta::create(JudgeNormalBox::$EXPECTED_OUTPUT_PORT_KEY, "file", "varB")));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#(undefined|not present)#i');
  }

  public function testMultipleOutputsOfVariable() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("varA", "file", "a.txt")));

    $boxAMeta = new BoxMeta();
    $boxAMeta->setName("gcc_a");
    $boxAMeta->addOutputPort(new FilePort(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file", "varA")));
    $pipeline->set(new GccCompilationBox($boxAMeta));

    $boxBMeta = new BoxMeta();
    $boxBMeta->setName("gcc_b");
    $boxBMeta->addOutputPort(new FilePort(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file", "varA")));
    $pipeline->set(new GccCompilationBox($boxBMeta));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#multiple ports output#i');
  }

  public function testNoOutputOfVariable() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("varA", "file", "")));

    $dataOutBoxMeta = new BoxMeta();
    $dataOutBoxMeta->setName("output");
    $dataOutBoxMeta->addInputPort(new FilePort(PortMeta::create(DataOutBox::$DATA_OUT_PORT_KEY, "file", "varA")));
    $pipeline->set(new DataOutBox($dataOutBoxMeta));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#no port outputs#i');
  }

  public function testNoOutputOfVariableWithDefault() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable(VariableMeta::create("varA", "file", "a.txt")));

    $dataOutBoxMeta = new BoxMeta();
    $dataOutBoxMeta->setName("output");
    $dataOutBoxMeta->addInputPort(new FilePort(PortMeta::create(DataOutBox::$DATA_OUT_PORT_KEY, "file", "varA")));
    $pipeline->set(new DataOutBox($dataOutBoxMeta));

    Assert::noError(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    });
  }
}

# Testing methods run
$testCase = new TestPipelineValidator;
$testCase->run();
