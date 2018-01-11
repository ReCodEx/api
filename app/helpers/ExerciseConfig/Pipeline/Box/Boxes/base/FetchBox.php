<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
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
   * @param string[] $remoteFiles
   * @param string[] $files
   * @return Task[]
   */
  protected function compileInternal(array $remoteFiles, array $files): array {
    $tasks = [];
    for ($i = 0; $i < count($files); ++$i) {
      $task = new Task();
      $task->setPriority(Priorities::$DEFAULT);

      // remote file has to have fetch task
      $task->setCommandBinary(TaskCommands::$FETCH);
      $task->setCommandArguments([
        $remoteFiles[$i],
        $files[$i]
      ]);

      // add task to result
      $tasks[] = $task;
    }
    return $tasks;
  }

}
