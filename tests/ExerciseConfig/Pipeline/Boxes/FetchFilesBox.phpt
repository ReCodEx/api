<?php

include '../../../bootstrap.php';

use App\Exceptions\ExerciseCompilationException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\FetchFilesBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;

/**
 * @testCase
 */
class TestFetchFilesBox extends Tester\TestCase
{
  /**
   * @var FetchFilesBox
   */
  private $box;

  public function __construct() {
    $inputPort = (new Port((new PortMeta())->setName(FetchFilesBox::$REMOTE_PORT_KEY)->setType(VariableTypes::$REMOTE_FILE_ARRAY_TYPE)));
    $inputPort->setVariableValue(new Variable(VariableTypes::$REMOTE_FILE_ARRAY_TYPE, "", ["1.in", "2.in"]));

    $outputPort = (new Port((new PortMeta())->setName(FetchFilesBox::$INPUT_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)));
    $outputPort->setVariableValue(new Variable(VariableTypes::$FILE_ARRAY_TYPE, "", ["1.in", "2.in"]));

    $meta = (new BoxMeta())->addInputPort($inputPort)->addOutputPort($outputPort);
    $this->box = new FetchFilesBox($meta);
    $this->box->validateMetadata();
  }


  public function testSameFileProvidedByUser() {
    Assert::exception(function () {
      $params = CompilationParams::create(["2.in"]);
      $this->box->compile($params);
    }, ExerciseCompilationException::class, "Exercise compilation error - File '2.in' is already defined by author of the exercise");
  }

  public function testCorrect() {
    $tasks = $this->box->compile(CompilationParams::create(["input.in"]));
    Assert::count(2, $tasks);

    Assert::equal("fetch", $tasks[0]->getCommandBinary());
    Assert::equal(['1.in', '${SOURCE_DIR}/1.in'], $tasks[0]->getCommandArguments());
    Assert::equal(Priorities::$DEFAULT, $tasks[0]->getPriority());

    Assert::equal("fetch", $tasks[1]->getCommandBinary());
    Assert::equal(['2.in', '${SOURCE_DIR}/2.in'], $tasks[1]->getCommandArguments());
    Assert::equal(Priorities::$DEFAULT, $tasks[1]->getPriority());
  }

}

# Testing methods run
$testCase = new TestFetchFilesBox();
$testCase->run();
