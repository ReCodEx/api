<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileOutBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\GccCompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JudgeBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Validation\PipelineValidator;
use App\Helpers\ExerciseConfig\Variable;
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
    $pipeline->getVariablesTable()->set(new Variable("file", "input", ""));
    $pipeline->getVariablesTable()->set(new Variable("file", "output", ""));

    $dataInBoxMeta = new BoxMeta();
    $dataInBoxMeta->setName("input");
    $dataInBoxMeta->addOutputPort(new Port(PortMeta::create(FileInBox::$FILE_IN_PORT_KEY, "file", "input")));
    $pipeline->set(new FileInBox($dataInBoxMeta));

    $compileBoxMeta = new BoxMeta();
    $compileBoxMeta->setName("compile");
    $compileBoxMeta->addInputPort(new Port(PortMeta::create(GccCompilationBox::$SOURCE_FILES_PORT_KEY, "file",
      "input")));
    $compileBoxMeta->addOutputPort(new Port(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file",
      "output")));
    $pipeline->set(new GccCompilationBox($compileBoxMeta));

    $dataOutBoxMeta = new BoxMeta();
    $dataOutBoxMeta->setName("output");
    $dataOutBoxMeta->addInputPort(new Port(PortMeta::create(FileOutBox::$FILE_OUT_PORT_KEY, "file", "output")));
    $pipeline->set(new FileOutBox($dataOutBoxMeta));

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
    $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));
    $pipeline->getVariablesTable()->set(new Variable("file", "varB", "b.txt"));

    $varAInMeta = new BoxMeta();
    $varAInMeta->setName("varA_in");
    $varAInMeta->addOutputPort(new Port(PortMeta::create("varA_in", "file", "varA")));
    $pipeline->set(new FileInBox($varAInMeta));

    $varBInMeta = new BoxMeta();
    $varBInMeta->setName("varB_in");
    $varBInMeta->addOutputPort(new Port(PortMeta::create("varB_in", "file", "varB")));
    $pipeline->set(new FileInBox($varBInMeta));

    $judgeBoxMeta = new BoxMeta();
    $pipeline->set(new JudgeBox($judgeBoxMeta));
    $judgeBoxMeta->setName("judge");
    $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$ACTUAL_OUTPUT_PORT_KEY, "file", "varA")));
    $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$EXPECTED_OUTPUT_PORT_KEY, "file", "varA")));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#(No port uses variable|Unused variable)#i');
  }

  public function testUndefinedVariable() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));
    $judgeBoxMeta = new BoxMeta();
    $pipeline->set(new JudgeBox($judgeBoxMeta));
    $judgeBoxMeta->setName("judge");
    $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$ACTUAL_OUTPUT_PORT_KEY, "file", "varA")));
    $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$EXPECTED_OUTPUT_PORT_KEY, "file", "varB")));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#(undefined|not present)#i');
  }

  public function testMultipleOutputsOfVariable() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));

    $boxAMeta = new BoxMeta();
    $boxAMeta->setName("gcc_a");
    $boxAMeta->addOutputPort(new Port(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file", "varA")));
    $pipeline->set(new GccCompilationBox($boxAMeta));

    $boxBMeta = new BoxMeta();
    $boxBMeta->setName("gcc_b");
    $boxBMeta->addOutputPort(new Port(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file", "varA")));
    $pipeline->set(new GccCompilationBox($boxBMeta));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#multiple ports output#i');
  }

  public function testNoOutputOfVariable() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable("file", "varA", ""));

    $dataOutBoxMeta = new BoxMeta();
    $dataOutBoxMeta->setName("output");
    $dataOutBoxMeta->addInputPort(new Port(PortMeta::create(FileOutBox::$FILE_OUT_PORT_KEY, "file", "varA")));
    $pipeline->set(new FileOutBox($dataOutBoxMeta));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#no port outputs#i');
  }

  public function testNoOutputOfVariableWithDefault() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));

    $dataOutBoxMeta = new BoxMeta();
    $dataOutBoxMeta->setName("output");
    $dataOutBoxMeta->addInputPort(new Port(PortMeta::create(FileOutBox::$FILE_OUT_PORT_KEY, "file", "varA")));
    $pipeline->set(new FileOutBox($dataOutBoxMeta));

    Assert::noError(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    });
  }

  public function testTypeMismatch() {
    $pipeline = new Pipeline();
    $pipeline->getVariablesTable()->set(new Variable("string", "input", "a.txt"));

    $compileBoxMeta = new BoxMeta();
    $compileBoxMeta->setName("compile");
    $compileBoxMeta->addInputPort(new Port(PortMeta::create(GccCompilationBox::$SOURCE_FILES_PORT_KEY, "file",
      "input")));
    $pipeline->set(new GccCompilationBox($compileBoxMeta));

    Assert::exception(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    }, ExerciseConfigException::class, '#type#i');
  }
}

# Testing methods run
$testCase = new TestPipelineValidator;
$testCase->run();
