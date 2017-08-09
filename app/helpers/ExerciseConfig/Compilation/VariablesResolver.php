<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\Node;


/**
 * Internal exercise configuration compilation service. This one is supposed
 * to resolve references to variables and fill them directly in ports in boxes.
 * This way next compilation services can compare boxes or directly assign
 * variable values during boxes compilation.
 */
class VariablesResolver {

  /**
   * Input boxes has to be treated differently. Variables can be loaded from
   * external configuration - environment config or exercise config.
   * @note Has to be called before @ref resolveForOtherNodes()
   * @param MergeTree $mergeTree
   */
  private function resolveForInputNodes(MergeTree $mergeTree) {
    foreach ($mergeTree->getInputNodes() as $node) {
      // input data box should have only one output port, that is why current is sufficient
      $outputPort = current($node->getBox()->getOutputPorts());
      $varName = $outputPort->getVariable();
      $nextNode = current($node->getChildren());

      // try to look for variable in environment config table
      $variable = null;

      // if variable still not present look in the exercise config table

      // assign variable to both nodes
      $outputPort->setVariableValue($variable);
      $nextNode->todo(); // @todo: children and parent in node has to be indexed with port name
    }
  }

  /**
   * Values for variables is taken only from pipeline variables table.
   * This procedure should also process all output boxes.
   * @note Has to be called after @ref resolveForInputNodes()
   * @param MergeTree $mergeTree
   */
  private function resolveForOtherNodes(MergeTree $mergeTree) {
    foreach ($mergeTree->getOtherNodes() as $node) {
      ;
    }
  }

  /**
   * Go through given array and resolve variables in boxes.
   * @param MergeTree[] $tests
   * @return MergeTree[]
   */
  public function resolve(array $tests): array {
    foreach ($tests as $mergeTree) {
      $this->resolveForInputNodes($mergeTree);
      $this->resolveForOtherNodes($mergeTree);
    }
  }

}
