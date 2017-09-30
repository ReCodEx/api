<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Abstract class representing exporting resource from pipeline.
 */
abstract class DataOutBox extends Box
{

  /**
   * DataOutBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }

  /**
   * Files should be exported out of pipeline. Compile box to set of tasks
   * copying files to results folder.
   * @param CompilationParams $params
   * @param Variable $output
   * @return array
   */
  protected function compileInternal(CompilationParams $params, Variable $output): array {
    if ($params->isDebug() === false || $output->isEmpty()) {
      return [];
    }

    $tasks = [];
    $filenames = $output->getValueAsArray();
    $files = $output->getPrefixedValueAsArray(ConfigParams::$SOURCE_DIR);
    for ($i = 0; $i < count($filenames); $i++) {
      $task = new Task;
      $task->setCommandBinary(TaskCommands::$COPY);
      $task->setCommandArguments([
        $files[$i],
        ConfigParams::$RESULT_DIR . $filenames[$i]
      ]);
      $tasks[] = $task;
    }

    return $tasks;
  }

}
