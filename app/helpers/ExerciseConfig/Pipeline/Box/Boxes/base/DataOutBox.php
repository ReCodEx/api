<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
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


  public function getCategory(): string {
    return BoxCategories::$INNER;
  }

  /**
   * Noop.
   * @param CompilationParams $params
   * @param Variable $output
   * @return array
   */
  protected function compileInternal(CompilationParams $params, Variable $output): array {
    return [];
  }

}
