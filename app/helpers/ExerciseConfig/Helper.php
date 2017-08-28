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
   * @return Variable[]
   */
  public function getVariablesForExercise(array $pipelines, VariablesTable $environmentVariables): array {
    $result = [];

    // process input variables from pipelines
    $inputs = []; // pairs of pipeline identifier and variable indexed by variable name
    $outputs = []; // pairs of pipeline identifier and variable indexed by variable name
    foreach ($pipelines as $pipelineId => $pipeline) {
      $result[$pipelineId] = [];

      // @todo
      //$pipeline->getDataInBoxes();
      //$pipeline->getDataOutBoxes();
    }

    // go through inputs and assign them to result
    foreach ($inputs as $pair) {
      $result[$pair[0]][] = $pair[1];
    }
    return $result;
  }

}
