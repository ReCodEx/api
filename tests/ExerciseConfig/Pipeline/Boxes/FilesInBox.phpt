<?php

include '../../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\FilesInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;

/**
 * @testCase
 */
class TestFilesInBox extends Tester\TestCase
{
  /**
   * @var FilesInBox
   */
  private $box;

  public function __construct() {
    $inputVariable = new Variable(VariableTypes::$REMOTE_FILE_ARRAY_TYPE, "", ["remote.1.in", "remote.2.in"]);

    $outputPort = (new Port((new PortMeta())->setName(FilesInBox::$FILES_IN_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)));
    $outputPort->setVariableValue(new Variable(VariableTypes::$FILE_ARRAY_TYPE, "", ["1.in", "2.in"]));

    $meta = (new BoxMeta())->addOutputPort($outputPort);
    $this->box = new FilesInBox($meta);
    $this->box->setInputVariable($inputVariable);
    $this->box->validateMetadata();
  }


  public function testRemoteAndLocalDifferentCount() {
    Assert::exception(function () {
      $this->box->getInputVariable()->setValue(["first"]);
      $this->box->compile(CompilationParams::create());
    }, ExerciseConfigException::class, "Exercise configuration error - Different count of remote variables and local variables in box ''");
  }

  public function testRemoteFileAndSameFileProvidedByUser() {
    Assert::exception(function () {
      $params = CompilationParams::create(["2.in"]);
      $this->box->compile($params);
    }, ExerciseConfigException::class, "Exercise configuration error - File '2.in' is already defined by author of the exercise");
  }

  public function testRemoteCorrect() {
    $tasks = $this->box->compile(CompilationParams::create(["input.in"]));
    Assert::count(2, $tasks);

    Assert::equal("fetch", $tasks[0]->getCommandBinary());
    Assert::equal(['remote.1.in', '${SOURCE_DIR}/1.in'], $tasks[0]->getCommandArguments());
    Assert::equal(Priorities::$DEFAULT, $tasks[0]->getPriority());

    Assert::equal("fetch", $tasks[1]->getCommandBinary());
    Assert::equal(['remote.2.in', '${SOURCE_DIR}/2.in'], $tasks[1]->getCommandArguments());
    Assert::equal(Priorities::$DEFAULT, $tasks[1]->getPriority());
  }

}

# Testing methods run
(new TestFilesInBox())->run();
