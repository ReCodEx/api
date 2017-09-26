<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Abstract box representing external resource.
 */
abstract class DataInBox extends Box
{

  /**
   * If data for this box is remote, fill this with the right variable reference.
   * @var Variable
   */
  protected $inputVariable = null;


  /**
   * DataInBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  /**
   * Get remote variable.
   * @return Variable|null
   */
  public function getInputVariable(): ?Variable {
    return $this->inputVariable;
  }

  /**
   * Set remote variable corresponding to this box.
   * @param Variable|null $variable
   */
  public function setInputVariable(?Variable $variable) {
    $this->inputVariable = $variable;
  }

  /**
   * Compile task from given information.
   * @param bool $isRemote
   * @param string $input
   * @param string $local
   * @return Task
   */
  protected function compileTask(bool $isRemote, string $input, string $local): Task {
    $task = new Task();
    if ($isRemote) {
      // remote file has to have fetch task
      $task->setCommandBinary(TaskCommands::$FETCH);
      $task->setCommandArguments([
        $input,
        ConfigParams::$SOURCE_DIR . $local
      ]);
    } else {
      $task->setCommandBinary(TaskCommands::$COPY);
      $task->setCommandArguments([
        ConfigParams::$SOURCE_DIR . $input,
        ConfigParams::$SOURCE_DIR . $local
      ]);
    }
    return $task;
  }

}
