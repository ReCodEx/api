<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Variable;


/**
 * Internal pipeline validation service.
 */
class PipelineValidator {

  /**
   * Validate pipeline.
   * @param Pipeline $pipeline
   * @throws ExerciseConfigException
   */
  public function validate(Pipeline $pipeline) {
    $variables = $pipeline->getVariablesTable();

    // Check ports of all boxes
    foreach ($pipeline->getAll() as $box) {
      foreach ($box->getPorts() as $port) {
        if ($port->getVariable() === NULL || $port->getVariable() === "") {
          continue; // Empty port - no further validation is necessary
        }

        $variable = $variables->get($port->getVariable());
        if ($variable === NULL) {
          throw new ExerciseConfigException(sprintf(
            "Variable %s used in port %s is not present in the variable table",
            $port->getVariable(),
            $port->getName()
          ));
        }

        if ($variable->getType() !== $port->getType()) {
          throw new ExerciseConfigException(sprintf(
            "Port %s of box %s expects a variable of type %s, but supplied variable %s has type %s",
            $port->getName(),
            $box->getName(),
            $port->getType(),
            $variable->getName(),
            $variable->getType()
          ));
        }
      }
    }

    // Check if all variables are written and read by some ports
    /** @var Variable $variable */
    foreach ($variables as $variableName => $variable) {
      $variableUsedAsOutput = FALSE;
      $variableUsedAsInput = FALSE;

      foreach ($pipeline->getAll() as $box) {
        foreach ($box->getOutputPorts() as $outputPort) {
          if ($outputPort->getVariable() !== $variableName) {
            continue;
          }

          if (!$variableUsedAsOutput) {
            $variableUsedAsOutput = TRUE;
            continue;
          }

          throw new ExerciseConfigException(sprintf(
            "Multiple ports output variable %s",
            $variableName
          ));
        }

        foreach ($box->getInputPorts() as $inputPort) {
          if ($inputPort->getVariable() === $variableName) {
            $variableUsedAsInput = TRUE;
          }
        }
      }

      if (!$variableUsedAsOutput) {
        throw new ExerciseConfigException(sprintf("No port outputs variable %s", $variableName));
      }

      if (!$variableUsedAsInput) {
        throw new ExerciseConfigException(sprintf("No port uses variable %s", $variableName));
      }
    }
  }
}
