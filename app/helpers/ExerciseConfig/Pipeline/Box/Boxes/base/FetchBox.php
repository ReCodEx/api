<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Abstract box representing internal pipeline resource.
 */
abstract class FetchBox extends Box
{

  /**
   * DataInBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  /**
   * Compile task from given information.
   * @param Variable $remote
   * @param Variable $local
   * @param CompilationParams $params
   * @return Task[]
   * @throws ExerciseConfigException
   */
  protected function compileInternal(Variable $remote, Variable $local, CompilationParams $params): array {

    // variable is empty, this means there is no request to rename fetched
    // files, therefore we have to fill variable with remote file names
    if ($local->isEmpty()) {
      $local->setValue($remote->getValue());
    }

    // prepare arrays which will be processed
    $remoteFiles = array_values($remote->getValueAsArray());
    $files = array_values($local->getTestPrefixedValueAsArray(ConfigParams::$SOURCE_DIR));

    $tasks = [];
    for ($i = 0; $i < count($files); ++$i) {
      $file = $files[$i];
      $basename = basename($file);

      // check if soon-to-be fetched file does not collide with files given by user
      if (in_array($basename, $params->getFiles())) {
        throw new ExerciseConfigException("File '{$basename}' is already defined by author of the exercise");
      }

      // create task
      $task = new Task();
      $task->setPriority(Priorities::$DEFAULT);

      // remote file has to have fetch task
      $task->setCommandBinary(TaskCommands::$FETCH);
      $task->setCommandArguments([
        $remoteFiles[$i],
        $file
      ]);

      // add task to result
      $tasks[] = $task;
    }
    return $tasks;
  }

}
