<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;


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
   * @throws ExerciseConfigException
   */
  private function resolveForInputNodes(MergeTree $mergeTree) {
    foreach ($mergeTree->getInputNodes() as $node) {
      // input data box should have only one output port, that is why current is sufficient
      $outputPort = current($node->getBox()->getOutputPorts());
      $variableName = $outputPort->getVariable();
      $child = current($node->getChildren());
      $inputPortName = array_search($node, $child->getParents());

      if ($inputPortName === FALSE) {
        // input node not found in parents of the next one
        throw new ExerciseConfigException("Malformed tree - input node {$node->getBox()->getName()} not found in child {$child->getBox()->getName()}");
      }

      // try to look for variable in environment config table
      $variable = $node->getEnvironmentConfigVariables()->get($variableName);
      // @todo: resolve regexps which matches files given by students

      // if variable still not present look in the exercise config table
      if (!$variable) {
        $variable = $node->getExerciseConfigVariables()->get($variableName);
      }

      // something is really wrong there... just leave and do not look back
      if (!$variable) {
        throw new ExerciseConfigException("Variable $variableName could not be resolved");
      }

      // assign variable to both nodes
      $outputPort->setVariableValue($variable);
      $child->getBox()->getInputPort($inputPortName)->setVariableValue($variable);
    }
  }

  /**
   * Resolve variables from other nodes, that means nodes which are not input
   * ones. This is general method for handling parent -> children pairs.
   * @param PortNode $parent
   * @param PortNode $child
   * @param string $inPortName
   * @param string $outPortName
   * @throws ExerciseConfigException
   */
  private function resolveForVariable(PortNode $parent, PortNode $child,
      string $inPortName, string $outPortName) {

    // init
    $inPort = $child->getBox()->getInputPort($inPortName);
    $outPort = $parent->getBox()->getOutputPort($outPortName);

    // check if the ports was processed and processed correctly
    if ($inPort->getVariableValue() && $outPort->getVariableValue()) {
      return; // this port was already processed
    } else if ($inPort->getVariableValue() || $outPort->getVariableValue()) {
      // only one value is assigned... well this is weird
      throw new ExerciseConfigException("Malformed ports detected: $inPortName, $outPortName");
    }

    $variableName = $inPort->getVariable();
    if ($variableName !== $outPort->getVariable()) {
      throw new ExerciseConfigException("Malformed tree - variables in corresponding ports ($inPortName, $outPortName) do not matches");
    }

    // get the variable from the correct table
    $variable = $parent->getPipelineVariables()->get($variableName);

    // something's fishy here... better leave now
    if (!$variable) {
      throw new ExerciseConfigException("Variable $variableName could not be resolved");
    }

    // set variable to both proper ports in child and parent
    $inPort->setVariableValue($variable);
    $outPort->setVariableValue($variable);
  }

  /**
   * Values for variables is taken only from pipeline variables table.
   * This procedure should also process all output boxes.
   * @note Has to be called after @ref resolveForInputNodes()
   * @param MergeTree $mergeTree
   * @throws ExerciseConfigException
   */
  private function resolveForOtherNodes(MergeTree $mergeTree) {
    foreach ($mergeTree->getOtherNodes() as $node) {
      foreach ($node->getParents() as $inPortName => $parent) {
        $outPortName = array_search($node, $parent->getChildren());
        if ($outPortName === FALSE) {
          // I do not like what you got!
          throw new ExerciseConfigException("Malformed tree - node {$node->getBox()->getName()} not found in parent {$parent->getBox()->getName()}");
        }

        $this->resolveForVariable($parent, $node, $inPortName, $outPortName);
      }

      foreach ($node->getChildren() as $outPortName => $child) {
        $inPortName = array_search($node, $child->getParents());
        if ($inPortName === FALSE) {
          // Oh boy, here we go throwing exceptions again!
          throw new ExerciseConfigException("Malformed tree - node {$node->getBox()->getName()} not found in child {$child->getBox()->getName()}");
        }

        $this->resolveForVariable($node, $child, $inPortName, $outPortName);
      }
    }
  }

  /**
   * Go through given array and resolve variables in boxes.
   * @param MergeTree[] $tests
   * @throws ExerciseConfigException
   */
  public function resolve(array $tests) {
    foreach ($tests as $mergeTree) {
      $this->resolveForInputNodes($mergeTree);
      $this->resolveForOtherNodes($mergeTree);
    }
  }

}
