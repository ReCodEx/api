<?php

include '../../../bootstrap.php';

use App\Exceptions\ExerciseCompilationException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\FetchFileBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;


/**
 * @testCase
 */
class TestFetchFileBox extends Tester\TestCase
{
  /**
   * @var FetchFileBox
   */
  private $box;

  public function __construct() {
    $inputPort = (new Port((new PortMeta())->setName(FetchFileBox::$REMOTE_PORT_KEY)->setType(VariableTypes::$REMOTE_FILE_TYPE)));
    $inputPort->setVariableValue(new Variable(VariableTypes::$REMOTE_FILE_TYPE, "", "input.in"));

    $outputPort = (new Port((new PortMeta())->setName(FetchFileBox::$INPUT_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)));
    $outputPort->setVariableValue(new Variable(VariableTypes::$FILE_TYPE, "", "input.in"));

    $meta = (new BoxMeta())->addInputPort($inputPort)->addOutputPort($outputPort);
    $this->box = new FetchFileBox($meta);
    $this->box->validateMetadata();
  }


  public function testSameFileProvidedByUser() {
    Assert::exception(function () {
      $params = CompilationParams::create(["input.in"]);
      $this->box->compile($params);
    }, ExerciseCompilationException::class, "Exercise compilation error - File 'input.in' is already defined by author of the exercise");
  }

  public function testCorrect() {
    $tasks = $this->box->compile(CompilationParams::create());
    Assert::count(1, $tasks);

    $task = $tasks[0];
    Assert::equal("fetch", $task->getCommandBinary());
    Assert::equal(["input.in", '${SOURCE_DIR}/input.in'], $task->getCommandArguments());
    Assert::equal(Priorities::$DEFAULT, $task->getPriority());
  }

}

# Testing methods run
$testCase = new TestFetchFileBox();
$testCase->run();
