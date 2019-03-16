<?php

include '../../../bootstrap.php';

use App\Exceptions\ExerciseCompilationException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;


/**
 * @testCase
 */
class TestFileInBox extends Tester\TestCase
{
  /**
   * @var FileInBox
   */
  private $box;

  public function __construct() {
    $inputVariable = new Variable(VariableTypes::$REMOTE_FILE_TYPE, "", "remote.in");

    $outputPort = (new Port((new PortMeta())->setName(FileInBox::$FILE_IN_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)));
    $outputPort->setVariableValue(new Variable(VariableTypes::$FILE_TYPE, "", "input.in"));

    $meta = (new BoxMeta())->addOutputPort($outputPort);
    $this->box = new FileInBox($meta);
    $this->box->setInputVariable($inputVariable);
    $this->box->validateMetadata();
  }


  public function testRemoteFileAndSameFileProvidedByUser() {
    Assert::exception(function () {
      $params = CompilationParams::create(["input.in"]);
      $this->box->compile($params);
    }, ExerciseCompilationException::class, "Exercise compilation error - File 'input.in' is already defined by author of the exercise");
  }

  public function testRemoteCorrect() {
    $tasks = $this->box->compile(CompilationParams::create());
    Assert::count(1, $tasks);

    $task = $tasks[0];
    Assert::equal("fetch", $task->getCommandBinary());
    Assert::equal(['remote.in', '${SOURCE_DIR}/input.in'], $task->getCommandArguments());
    Assert::equal(Priorities::$DEFAULT, $task->getPriority());
  }

}

# Testing methods run
(new TestFileInBox())->run();
