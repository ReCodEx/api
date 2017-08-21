<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\VariablesTable;


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
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @throws ExerciseConfigException
   */
  public function resolveForInputNodes(MergeTree $mergeTree,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables) {
    foreach ($mergeTree->getInputNodes() as $node) {

      /** @var DataInBox $inputBox */
      $inputBox = $node->getBox();

      // input data box should have only one output port, that is why current is sufficient
      $outputPort = current($inputBox->getOutputPorts());
      $variableName = $outputPort->getVariable();
      $child = current($node->getChildren());
      $inputPortName = array_search($node, $child->getParents());

      if ($inputPortName === FALSE) {
        // input node not found in parents of the next one
        throw new ExerciseConfigException("Malformed tree - input node {$inputBox->getName()} not found in child {$child->getBox()->getName()}");
      }

      // try to look for variable in environment config table
      $remoteVariable = $environmentVariables->get($variableName);
      // @todo: resolve regexps which matches files given by students

      // if variable still not present look in the exercise config table
      if (!$remoteVariable) {
        $remoteVariable = $exerciseVariables->get($variableName);
      }

      // variable value in local pipeline config
      $variable = $pipelineVariables->get($variableName);
      // something is really wrong there... just leave and do not look back
      if (!$variable) {
        throw new ExerciseConfigException("Variable '$variableName' from input data box could not be resolved");
      }

      // assign variable to both nodes
      $inputBox->setRemoteVariable($remoteVariable);
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
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @throws ExerciseConfigException
   */
  private function resolveForVariable(PortNode $parent, PortNode $child,
      string $inPortName, string $outPortName,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables) {

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
    $variable = $pipelineVariables->get($variableName);
    // something's fishy here... better leave now
    if (!$variable) {
      throw new ExerciseConfigException("Variable '$variableName' could not be resolved");
    }

    // variable is reference, try to find its value in external variables tables
    if ($variable->isReference()) {
      $referenceName = $variable->getReference();
      $variable = $environmentVariables->get($referenceName);
      if (!$variable) {
        $variable = $exerciseVariables->get($referenceName);
      }

      // reference could not be found
      if (!$variable) {
        throw new ExerciseConfigException("Variable '$variableName' is reference which could not be resolved");
      }
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
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @throws ExerciseConfigException
   */
  public function resolveForOtherNodes(MergeTree $mergeTree,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables) {
    foreach ($mergeTree->getOtherNodes() as $node) {
      foreach ($node->getParents() as $inPortName => $parent) {
        $outPortName = $parent->findChildPort($node);
        if (!$outPortName) {
          // I do not like what you got!
          throw new ExerciseConfigException("Malformed tree - node {$node->getBox()->getName()} not found in parent {$parent->getBox()->getName()}");
        }

        $this->resolveForVariable($parent, $node, $inPortName, $outPortName, $environmentVariables, $exerciseVariables, $pipelineVariables);
      }

      foreach ($node->getChildrenByPort() as $outPortName => $children) {
        foreach ($children as $child) {
          $inPortName = $child->findParentPort($node);
          if (!$inPortName) {
            // Oh boy, here we go throwing exceptions again!
            throw new ExerciseConfigException("Malformed tree - node {$node->getBox()->getName()} not found in child {$child->getBox()->getName()}");
          }

          $this->resolveForVariable($node, $child, $inPortName, $outPortName, $environmentVariables, $exerciseVariables, $pipelineVariables);
        }
      }
    }
  }

  /**
   * Resolve variables for the whole given tree.
   * @param MergeTree $mergeTree
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   */
  public function resolve(MergeTree $mergeTree,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables) {
    $this->resolveForInputNodes($mergeTree, $environmentVariables, $exerciseVariables, $pipelineVariables);
    $this->resolveForOtherNodes($mergeTree, $environmentVariables, $exerciseVariables, $pipelineVariables);
  }

}
