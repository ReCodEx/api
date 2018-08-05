<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Customizable box for joing two pipelines in-between. There are only one input
 * port and only one output port, ports can be modified.
 * Default values for ports and name are not implemented.
 * @note Should be used only for internal purposes.
 */
class JoinPipelinesBox extends Box
{
  /** Type key */
  public static $JOIN_PIPELINES_BOX_TYPE = "join-pipelines";

  /**
   * JoinPipelinesBox constructor.
   * @param string $name
   */
  public function __construct(string $name = "") {
    parent::__construct((new BoxMeta())->setName($name));
  }


  /**
   * Set name of box.
   * @param string $name
   * @return JoinPipelinesBox
   */
  public function setName(string $name): JoinPipelinesBox {
    $this->meta->setName($name);
    return $this;
  }

  /**
   * Set input port of this box.
   * @param Port $port
   * @return JoinPipelinesBox
   */
  public function setInputPort(Port $port): JoinPipelinesBox {
    $this->meta->setInputPorts([$port]);
    return $this;
  }

  /**
   * Set output port of this box.
   * @param Port $port
   * @return JoinPipelinesBox
   */
  public function setOutputPort(Port $port): JoinPipelinesBox {
    $this->meta->setOutputPorts([$port]);
    return $this;
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$JOIN_PIPELINES_BOX_TYPE;
  }

  /**
   * Get default input ports for this box.
   * @return array
   */
  public function getDefaultInputPorts(): array {
    return array();
  }

  /**
   * Get default output ports for this box.
   * @return array
   */
  public function getDefaultOutputPorts(): array {
    return array();
  }

  /**
   * Get default name of this box.
   * @return string
   */
  public function getDefaultName(): string {
    return "";
  }


  /**
   * Compile box into set of low-level tasks.
   * @param CompilationParams $params
   * @return array
   * @throws ExerciseConfigException
   */
  public function compile(CompilationParams $params): array {
    /**
     * @var Variable $inputVariable
     * @var Variable $outputVariable
     */
    $inputVariable = current($this->getInputPorts())->getVariableValue();
    $outputVariable = current($this->getOutputPorts())->getVariableValue();

    if ($outputVariable->isEmpty()) {
      // output file or files is empty this means that renaming is not
      // requested, to maintain proper structure set files from input to output
      $outputVariable->setValue($inputVariable->getValue());
    }

    // check for emptiness or same values, in those cases nothing has to be done
    if (($inputVariable->getValue() === $outputVariable->getValue()) ||
      ($inputVariable->isEmpty() && $outputVariable->isEmpty())) {
      return [];
    }

    // prepare inputs and outputs
    if ($inputVariable->isValueArray() && $outputVariable->isValueArray()) {
      // both variable and input variable are arrays
      if (count($inputVariable->getValue()) !== count($outputVariable->getValue())) {
        throw new ExerciseConfigException("Different count of remote variables and local variables in box '{$this->getName()}'");
      }

      $inputs = $inputVariable->getTestPrefixedValue(ConfigParams::$SOURCE_DIR);
      $outputs = $outputVariable->getTestPrefixedValue(ConfigParams::$SOURCE_DIR);
    } else if (!$inputVariable->isValueArray() && !$outputVariable->isValueArray()) {
      // both variable values are single values
      $inputs = [$inputVariable->getTestPrefixedValue(ConfigParams::$SOURCE_DIR)];
      $outputs = [$outputVariable->getTestPrefixedValue(ConfigParams::$SOURCE_DIR)];
    } else {
      throw new ExerciseConfigException("Incompatible types of variables in joining box '{$this->getName()}'");
    }

    // better be safe
    $inputs = array_values($inputs);
    $outputs = array_values($outputs);

    // general foreach for processing both arrays and single elements
    $tasks = [];
    for ($i = 0; $i < count($inputs); ++$i) {
      if ($inputs[$i] === $outputs[$i]) {
        continue;
      }

      $task = new Task();
      $task->setPriority(Priorities::$DEFAULT);
      $task->setCommandBinary(TaskCommands::$RENAME);
      $task->setCommandArguments([
        $inputs[$i],
        $outputs[$i]
      ]);

      // add task to result
      $tasks[] = $task;
    }

    return $tasks;
  }

}
