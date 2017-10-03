<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Variable;
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
   * General compilation for both FilesInBox and FileInBox. Initial checks
   * have to be performed before calling this function.
   * @param Variable|null $inputVariable
   * @param Variable $variable
   * @return array
   * @throws ExerciseConfigException
   */
  protected function compileInternal(?Variable $inputVariable,
      Variable $variable): array {

    // preparations
    $isRemote = $inputVariable && $inputVariable->isRemoteFile();
    if (!$inputVariable) {
      // input variable was not given, which is fine, treatment in validation
      // which takes place next can be applied even in this situation,
      // getValue and getPrefixedValue are sufficient and correct here
      $inputVariable = $variable;
    }

    if (($inputVariable->getValue() === $variable->getPrefixedValue()) ||
      ($inputVariable->isEmpty() && $variable->isEmpty())) {
      // there are no files which should be renamed
      return [];
    }

    // variable is empty, this means that there is no request to rename fetched
    // files, therefore we have to fill variable with remote file names
    if ($variable->isEmpty()) {
      $variable->setValuePrefix($inputVariable->getValuePrefix());
      $variable->setValue($inputVariable->getValue());
    }

    // prepare arrays which will be processed
    $inputFiles = $inputVariable->getValueAsArray();
    $files = $variable->getPrefixedValueAsArray();

    // counts are not the same, this is really bad situation, end it now!
    if (count($inputFiles) !== count($files)) {
      throw new ExerciseConfigException(sprintf("Different count of remote variables and local variables in box '%s'", $this->getName()));
    }

    // general foreach for both local and remote files
    $tasks = [];
    for ($i = 0; $i < count($files); ++$i) {
      $task = $this->compileTask($isRemote, $inputFiles[$i], $files[$i]);
      $tasks[] = $task;
    }
    return $tasks;
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
