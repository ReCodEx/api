<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\Wildcards;
use Nette\Utils\Arrays;


/**
 * Internal exercise configuration compilation service. This one is supposed
 * to resolve references to variables and fill them directly in ports in boxes.
 * This way next compilation services can compare boxes or directly assign
 * variable values during boxes compilation.
 */
class VariablesResolver {

  /**
   * Variables defined in exercise config or environment config can contain
   * references, these references are resolved against user submitted variable
   * values. And that is, surprisingly, what this method does.
   * @param Variable|null $variable
   * @param CompilationParams $params
   * @throws ExerciseConfigException
   */
  private function resolveSubmitVariableReference(?Variable $variable, CompilationParams $params) {
    if (!$variable || !$variable->isReference()) {
      return;
    }

    // author of exercise defined reference which should be provided on submit,
    // but user which submitted solution did not provide the value for reference
    $reference = $variable->getReference();
    if (!array_key_exists($reference, $params->getVariables())) {
      throw new ExerciseConfigException("Variable '{$reference}' was not provided on submit");
    }

    // set user provided variable to actual variable
    $variable->setValue($params->getVariables()[$reference]);

    // files can be further validated on existence
    if ($variable->isFile()) {
      foreach ($variable->getValueAsArray() as $value) {
        if (!in_array($value, $params->getFiles())) {
          throw new ExerciseConfigException("File '{$value}' in variable '{$reference}' could not be found among submitted files");
        }
      }
    }
  }

  /**
   * Regular expressions are allowed only in file inputs and should be resolved
   * against files given during submission.
   * @param Variable|null $variable
   * @param string[] $submittedFiles
   * @return Variable|null
   * @throws ExerciseConfigException
   */
  private function resolveFileInputsRegexp(?Variable $variable,
      array $submittedFiles): ?Variable {
    if (!$variable || $variable->isReference() || !$variable->isFile() || $variable->isValueArray()) {
      // variable is null, reference or variable is not file or value is already array,
      // then no regexp matching is needed
      return $variable;
    }

    // regexp matching of all files against variable value
    $value = $variable->getValue();
    $matches = array_filter($submittedFiles, function (string $file) use ($value) {
      return Wildcards::match($value, $file);
    });

    if (empty($matches)) {
      // there were no matches, but variable value cannot be empty!
      throw new ExerciseConfigException("None of the submitted files matched regular expression '{$value}' in variable '{$variable->getName()}'");
    }

    // construct resulting variable from given variable info
    $result = (new Variable($variable->getType()))->setName($variable->getName());
    if ($variable->isArray()) {
      $result->setValue($matches);
    } else {
      // variable is not an array, so take only first element from all matches
      $result->setValue(current($matches));
    }

    return $result;
  }

  /**
   * For given array of values try to find corresponding indices in given files
   * array and return appropriate values from files array.
   * @param array $values
   * @param array $files
   * @return array resolved files
   * @throws ExerciseConfigException
   */
  private function resolveRemoteFileHashValue(array $values, array $files): array {
    $newValues = [];
    foreach ($values as $value) {
      if (!array_key_exists($value, $files)) {
        throw new ExerciseConfigException("File '{$value}' does not exist in exercise or pipeline.");
      }

      $newValues[] = $files[$value];
    }

    return $newValues;
  }

  /**
   * If variable is of type remote-file resolve value which contains file name and replace it with hashes.
   * @param Variable|null $variable
   * @param array $files indexed by file names, containing file hashes
   * @throws ExerciseConfigException
   */
  private function resolveRemoteFileHash(?Variable $variable, array $files) {
    if (!$variable || $variable->isEmpty() || !$variable->isRemoteFile()) {
      // unfitting variable
      return;
    }

    if ($variable->isValueArray()) {
      $variable->setValue($this->resolveRemoteFileHashValue($variable->getValue(), $files));
    } else {
      $variable->setValue(current($this->resolveRemoteFileHashValue($variable->getValueAsArray(), $files)));
    }
  }

  /**
   * Input boxes has to be treated differently. Variables can be loaded from
   * external configuration - environment config or exercise config.
   * @note Has to be called before @ref resolveForOtherNodes()
   * @param MergeTree $mergeTree
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @throws ExerciseConfigException
   */
  public function resolveForInputNodes(MergeTree $mergeTree, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables, CompilationContext $context, CompilationParams $params) {
    foreach ($mergeTree->getInputNodes() as $node) {

      /** @var DataInBox $inputBox */
      $inputBox = $node->getBox();

      // input data box should have only one output port, that is why current is sufficient
      $outputPort = current($inputBox->getOutputPorts());
      $variableName = $outputPort->getVariable();
      $child = current($node->getChildren());
      if ($child === false) {
        throw new ExerciseConfigException("Input port not found for variable {$variableName}");
      }

      $inputPortName = array_search($node, $child->getParents());
      if ($inputPortName === false) {
        // input node not found in parents of the next one
        throw new ExerciseConfigException("Malformed tree - input node '{$inputBox->getName()}' not found in child '{$child->getBox()->getName()}'");
      }

      // variable value in local pipeline config
      $variable = $pipelineVariables->get($variableName);
      if (!$variable) {
        // something is really wrong there... just leave and do not look back
        throw new ExerciseConfigException("Variable '$variableName' from input data box could not be resolved");
      }

      // find references
      $variable = $this->findReferenceIfAny($variable, $context->getEnvironmentConfigVariables(), $exerciseVariables, $params);

      // try to look for remote variable in configuration tables
      $inputVariable = null;
      $environmentVariable = $context->getEnvironmentConfigVariables()->get($variableName);
      $exerciseVariable = $exerciseVariables->get($variableName);
      if ($environmentVariable) {
        $inputVariable = $this->resolveFileInputsRegexp($environmentVariable, $params->getFiles());

        // resolve references which might be in environment config
        $this->resolveSubmitVariableReference($inputVariable, $params);
      } else if ($exerciseVariable) {
        $inputVariable = $exerciseVariable;
      }

      // resolve name of the file to the hash if variable is remote file
      $this->resolveRemoteFileHash($inputVariable, $context->getExerciseFiles());

      // assign variable to both nodes
      $inputBox->setInputVariable($inputVariable);
      $outputPort->setVariableValue($variable);
      $child->getBox()->getInputPort($inputPortName)->setVariableValue($variable);
    }
  }

  /**
   * If variable is reference, try to find it in given variables tables.
   * @param Variable $variable
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param CompilationParams $params
   * @return Variable
   * @throws ExerciseConfigException
   */
  private function findReferenceIfAny(Variable $variable,
      VariablesTable $environmentVariables,
      VariablesTable $exerciseVariables,
      CompilationParams $params): Variable {
    if ($variable->isReference()) {
      $referenceName = $variable->getReference();
      $variable = $environmentVariables->get($referenceName);
      if ($variable) {
        // resolve references which might be in environment config
        $this->resolveSubmitVariableReference($variable, $params);
      } else {
        $variable = $exerciseVariables->get($referenceName);
      }

      // reference could not be found
      if (!$variable) {
        throw new ExerciseConfigException("Variable reference '{$referenceName}' could not be resolved");
      }
    }

    return $variable;
  }

  /**
   * Resolve variables from other nodes, that means nodes which are not input
   * ones. This is general method for handling parent -> children pairs.
   * @note Parent and outPortName can be null
   * @param PortNode|null $parent
   * @param PortNode $child
   * @param string $inPortName
   * @param string|null $outPortName
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @param array $pipelineFiles
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @throws ExerciseConfigException
   */
  private function resolveForVariable(?PortNode $parent, PortNode $child,
      string $inPortName, ?string $outPortName, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables, array $pipelineFiles,
      CompilationContext $context, CompilationParams $params) {

    // init
    $inPort = $child->getBox()->getInputPort($inPortName);
    $outPort = $parent === null ? null : $parent->getBox()->getOutputPort($outPortName);

    // check if the ports was processed and processed correctly
    if ($inPort->getVariableValue() !== null) {
      return; // this port was already processed
    } else if ($inPort->getVariableValue() === null && $outPort && $outPort->getVariableValue() !== null) {
      // only input value is assigned... this means it was process before with
      // some other child, so just assign value and return
      $inPort->setVariableValue($outPort->getVariableValue());
      return;
    }

    $variableName = $inPort->getVariable();
    if (empty($variableName)) {
      // variable is either null or empty, this means that we do not have to
      // process it and can safely return
      return;
    }

    // check if variable name is the same in both ports
    if ($outPort !== null && $variableName !== $outPort->getVariable()) {
      throw new ExerciseConfigException("Malformed tree - variables in corresponding ports ($inPortName, $outPortName) do not matches");
    }

    // get the variable from the correct table
    $variable = $pipelineVariables->get($variableName);
    // something's fishy here... better leave now
    if (!$variable) {
      throw new ExerciseConfigException("Variable '$variableName' could not be resolved");
    }

    // variable is reference, try to find its value in external variables tables
    $pipelineVariable = $variable;
    $variable = $this->findReferenceIfAny($variable, $context->getEnvironmentConfigVariables(), $exerciseVariables, $params);

    // resolve name of the file to the hash if variable is remote file
    $this->resolveRemoteFileHash($variable, $pipelineFiles);

    if ($pipelineVariable !== $variable) {
      // pipeline variable was reference thus we loaded it from exercise or
      // environment config, this means it can contain further references
      // to submitted variables in solution
      $this->resolveSubmitVariableReference($variable, $params);
    }

    // set variable to both proper ports in child and parent
    $inPort->setVariableValue($variable);
    if ($outPort !== null) { $outPort->setVariableValue($variable); }
  }

  /**
   * Values for variables is taken only from pipeline variables table.
   * This procedure should also process all output boxes.
   * @note Has to be called after @ref resolveForInputNodes()
   * @param MergeTree $mergeTree
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @param array $pipelineFiles
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @throws ExerciseConfigException
   */
  private function resolveForOtherNodes(MergeTree $mergeTree, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables, array $pipelineFiles, CompilationContext $context,
      CompilationParams $params) {
    foreach ($mergeTree->getOtherNodes() as $node) {
      foreach ($node->getBox()->getInputPorts() as $inPortName => $inputPort) {
        $parent = $node->getParent($inPortName);
        $outPortName = $parent === null ? null : $parent->findChildPort($node);
        if ($parent !== null && $outPortName === null) {
          // I do not like what you got!
          throw new ExerciseConfigException("Malformed tree - node '{$node->getBox()->getName()}' not found in parent '{$parent->getBox()->getName()}'");
        }

        $this->resolveForVariable($parent, $node, $inPortName, $outPortName, $exerciseVariables, $pipelineVariables, $pipelineFiles, $context, $params);
      }

      foreach ($node->getChildrenByPort() as $outPortName => $children) {
        foreach ($children as $child) {
          $inPortName = $child->findParentPort($node);
          if (!$inPortName) {
            // Oh boy, here we go throwing exceptions again!
            throw new ExerciseConfigException("Malformed tree - node '{$node->getBox()->getName()}' not found in child '{$child->getBox()->getName()}'");
          }

          $this->resolveForVariable($node, $child, $inPortName, $outPortName, $exerciseVariables, $pipelineVariables, $pipelineFiles, $context, $params);
        }
      }
    }
  }

  /**
   * Resolve variables for the whole given tree.
   * @param MergeTree $mergeTree
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @param array $pipelineFiles indexed by file names, contains file hashes
   * @param CompilationContext $context
   * @param CompilationParams $params
   * @throws ExerciseConfigException
   */
  public function resolve(MergeTree $mergeTree, VariablesTable $exerciseVariables, VariablesTable $pipelineVariables,
      array $pipelineFiles, CompilationContext $context, CompilationParams $params) {
    $this->resolveForInputNodes($mergeTree, $exerciseVariables, $pipelineVariables, $context, $params);
    $this->resolveForOtherNodes($mergeTree, $exerciseVariables, $pipelineVariables, $pipelineFiles, $context, $params);
  }

}
