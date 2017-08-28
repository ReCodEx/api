<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Ports;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\VariableTypes;


/**
 * Port factory which should be used for user inputs.
 */
class PortFactory
{
  /**
   * Based on given meta information construct proper variable type.
   * @param PortMeta $meta
   * @return Port
   * @throws ExerciseConfigException
   */
  public function create(PortMeta $meta): Port {
    if ($meta->getType() === VariableTypes::$UNDEFINED_TYPE) {
      throw new ExerciseConfigException("Undefined port not allowed in user input");
    }
    return new Port($meta);
  }

}
