<?php

namespace App\Helpers\ExerciseConfig;


/**
 * Helper class where some handy functions regarding exercise configuration
 * can be declared.
 */
class Helper {

  /**
   * Get variables which exercise configuration should include for given
   * pipelines. During computations pipelines are merged and variables checked
   * against environment configuration. Only inputs are returned as result.
   * @param Pipeline[] $pipelines
   * @param VariablesTable $environmentVariables
   * @return array
   */
  public function getVariablesForExercise(array $pipelines, VariablesTable $environmentVariables): array {
    $result = [];

    // process input variables from pipelines
    $inputs = []; // pairs of pipeline identifier and port indexed by variable name
    $references = []; // pairs of pipeline identifier and variable indexed by variable name
    $outputs = []; // pairs of pipeline identifier and port indexed by variable name
    foreach ($pipelines as $pipelineId => $pipeline) {
      $result[$pipelineId] = [];

      // load pipeline input variables
      $localInputs = [];
      foreach ($pipeline->getDataInBoxes() as $dataInBox) {
        foreach ($dataInBox->getOutputPorts() as $outputPort) {
          if ($environmentVariables->get($outputPort->getVariable())) {
            // variable is defined in environment variables
            continue;
          }
          $localInputs[$outputPort->getVariable()] = [$pipelineId, $outputPort];
        }
      }

      // find reference variables and add them to inputs
      foreach ($pipeline->getVariablesTable()->getAll() as $variable) {
        if ($variable->isReference() &&
            !$environmentVariables->get($variable->getName())) {
          $references[$variable->getName()] = [$pipelineId, $variable];
        }
      }

      // load pipeline output variables
      $localOutputs = [];
      foreach ($pipeline->getDataOutBoxes() as $dataOutBox) {
        foreach ($dataOutBox->getInputPorts() as $inputPort) {
          $localOutputs[$inputPort->getVariable()] = [$pipelineId, $inputPort];
        }
      }

      // remove variables which join pipelines
      foreach (array_keys($outputs) as $variableName) {
        if (!array_key_exists($variableName, $localInputs)) {
          // output variable cannot be found in local input variables, this
          // means that output variable is not joining with any input variable,
          // at least for now
          continue;
        }

        unset($outputs[$variableName]);
        unset($localInputs[$variableName]);
      }

      // merge variables
      $inputs = array_merge($inputs, $localInputs);
      $outputs = array_merge($outputs, $localOutputs);
    }

    // go through inputs and assign them to result
    foreach ($inputs as $variableName => $pair) {
      $port = $pair[1];
      if ($port->isFile() && $port->isArray()) {
        // port is file and also array, in exercise config if there should be
        // defined file as variable it is expected to be remote file, so the
        // remote file type is offered back to web-app
        $variable = new Variable((new VariableMeta)->setName($variableName)
          ->setType(VariableTypes::$REMOTE_FILE_ARRAY_TYPE));
      } else if ($port->isFile() && !$port->isArray()) {
        // port is file and not an array, in exercise config if there should be
        // defined file as variable it is expected to be remote file, so the
        // remote file type is offered back to web-app
        $variable = new Variable((new VariableMeta)->setName($variableName)
          ->setType(VariableTypes::$REMOTE_FILE_TYPE));
      } else {
        $variable = new Variable((new VariableMeta)->setName($variableName)
          ->setType($port->getType()));
      }
      $result[$pair[0]][] = $variable;
    }
    foreach ($references as $pair) {
      $variableName = $pair[1]->getReference();
      $variable = new Variable((new VariableMeta)->setName($variableName)
        ->setType($pair[1]->getType()));
      $result[$pair[0]][] = $variable;
    }
    return $result;
  }

}
