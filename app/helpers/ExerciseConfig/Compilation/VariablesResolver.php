<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseCompilationSoftException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\Wildcards;

/**
 * Internal exercise configuration compilation service. This one is supposed
 * to resolve references to variables and fill them directly in ports in boxes.
 * This way next compilation services can compare boxes or directly assign
 * variable values during boxes compilation.
 */
class VariablesResolver
{

    /**
     * Variables defined in exercise config or environment config can contain
     * references, these references are resolved against user submitted variable
     * values. And that is, surprisingly, what this method does.
     * @param Variable|null $variable
     * @param CompilationParams $params
     * @throws ExerciseConfigException
     * @throws ExerciseCompilationException
     */
    private function resolveSubmitVariableReference(?Variable $variable, CompilationParams $params)
    {
        if (!$variable || !$variable->isReference()) {
            return;
        }

        // author of exercise defined reference which should be provided on submit,
        // but user which submitted solution did not provide the value for reference
        $reference = $variable->getReference();
        if (!$params->getSolutionParams()->getVariable($reference)) {
            throw new ExerciseCompilationSoftException(
                "Variable '{$reference}' was not provided on submit",
                FrontendErrorMappings::E400_404__EXERCISE_COMPILATION_VARIABLE_NOT_PROVIDED,
                ["variable" => $reference]
            );
        }

        // set user provided variable to actual variable
        $variable->setValue($params->getSolutionParams()->getVariable($reference)->getValue());

        // files can be further validated on existence
        if ($variable->isFile()) {
            foreach ($variable->getValueAsArray() as $value) {
                if (!in_array($value, $params->getFiles())) {
                    throw new ExerciseCompilationSoftException(
                        "File '{$value}' in variable '{$reference}' could not be found among submitted files",
                        FrontendErrorMappings::E400_405__EXERCISE_COMPILATION_FILE_NOT_PROVIDED,
                        ["filename" => $value, "variable" => $reference]
                    );
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
     * @throws ExerciseCompilationException
     */
    private function resolveFileInputsRegexp(
        ?Variable $variable,
        array $submittedFiles
    ): ?Variable {
        if (!$variable || $variable->isReference() || !$variable->isFile() || $variable->isValueArray()) {
            // variable is null, reference or variable is not file or value is already array,
            // then no regexp matching is needed
            return $variable;
        }

        // regexp matching of all files against variable value
        $value = $variable->getValue();
        $matches = array_filter(
            $submittedFiles,
            function (string $file) use ($value) {
                return Wildcards::match($value, $file);
            }
        );

        if (empty($matches)) {
            // there were no matches, but variable value cannot be empty!
            throw new ExerciseCompilationSoftException(
                "None of the submitted files matched regular expression '{$value}' in variable '{$variable->getName()}'",
                FrontendErrorMappings::E400_403__EXERCISE_COMPILATION_VARIABLE_NOT_MATCHED,
                ["regex" => $value, "variable" => $variable->getName()]
            );
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
     * @throws ExerciseCompilationException
     */
    private function resolveRemoteFileHashValue(array $values, array $files): array
    {
        $newValues = [];
        foreach ($values as $value) {
            if (!array_key_exists($value, $files)) {
                throw new ExerciseCompilationException("File '{$value}' does not exist in exercise or pipeline.");
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
     * @throws ExerciseCompilationException
     */
    private function resolveRemoteFileHash(?Variable $variable, array $files)
    {
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
     * @throws ExerciseCompilationException
     */
    public function resolveForInputNodes(
        MergeTree $mergeTree,
        VariablesTable $exerciseVariables,
        VariablesTable $pipelineVariables,
        CompilationContext $context,
        CompilationParams $params
    ) {
        foreach ($mergeTree->getInputNodes() as $node) {
            /** @var DataInBox $inputBox */
            $inputBox = $node->getBox();
            $outputPort = current($inputBox->getOutputPorts());
            $variableName = $outputPort->getVariable();

            // input data box should have only one output port, but there can be multiple children
            if (count($node->getChildren()) === 0) {
                throw new ExerciseCompilationException("Input ports not found for variable '{$variableName}'");
            }

            // variable value in local pipeline config
            $variable = $pipelineVariables->get($variableName);
            if (!$variable) {
                // something is really wrong there... just leave and do not look back
                throw new ExerciseCompilationException(
                    "Variable '$variableName' from input data box could not be resolved"
                );
            }

            // find references
            $variable = $this->findReferenceIfAny(
                $variable,
                $context->getEnvironmentConfigVariables(),
                $exerciseVariables,
                $params
            );

            // try to look for remote variable in configuration tables
            $inputVariable = null;
            $environmentVariable = $context->getEnvironmentConfigVariables()->get($variableName);
            $exerciseVariable = $exerciseVariables->get($variableName);
            if ($environmentVariable) {
                $inputVariable = $this->resolveFileInputsRegexp($environmentVariable, $params->getFiles());
            } else {
                if ($exerciseVariable) {
                    $inputVariable = $exerciseVariable;
                }
            }

            // resolve references which might be in environment config
            $this->resolveSubmitVariableReference($inputVariable, $params);

            // resolve name of the file to the hash if variable is remote file
            $this->resolveRemoteFileHash($inputVariable, $context->getExerciseFiles());

            // assign variable to both nodes
            $inputBox->setInputVariable($inputVariable);
            $outputPort->setVariableValue($variable);

            // handle children and assignment of the variable to their ports
            foreach ($node->getChildren() as $child) {
                $inputPortName = array_search($node, $child->getParents());
                if ($inputPortName === false) {
                    // input node not found in parents of the next one
                    throw new ExerciseCompilationException(
                        "Malformed tree - input node '{$inputBox->getName()}' not found in child '{$child->getBox()->getName()}'"
                    );
                }

                $child->getBox()->getInputPort($inputPortName)->setVariableValue($variable);
            }
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
     * @throws ExerciseCompilationException
     */
    private function findReferenceIfAny(
        Variable $variable,
        VariablesTable $environmentVariables,
        VariablesTable $exerciseVariables,
        CompilationParams $params
    ): Variable {
        if ($variable->isReference()) {
            $referenceName = $variable->getReference();
            $variable = $environmentVariables->get($referenceName);
            if (!$variable) {
                $variable = $exerciseVariables->get($referenceName);
            }

            // resolve references which might be in environment config
            $this->resolveSubmitVariableReference($variable, $params);

            // reference could not be found
            if (!$variable) {
                throw new ExerciseCompilationException("Variable reference '{$referenceName}' could not be resolved");
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
     * @throws ExerciseCompilationException
     */
    private function resolveForVariable(
        ?PortNode $parent,
        PortNode $child,
        string $inPortName,
        ?string $outPortName,
        VariablesTable $exerciseVariables,
        VariablesTable $pipelineVariables,
        array $pipelineFiles,
        CompilationContext $context,
        CompilationParams $params
    ) {
        // init
        $inPort = $child->getBox()->getInputPort($inPortName);
        $outPort = $parent === null ? null : $parent->getBox()->getOutputPort($outPortName);

        // check if the ports was processed and processed correctly
        if ($inPort->getVariableValue() !== null) {
            return; // this port was already processed
        } else {
            if ($inPort->getVariableValue() === null && $outPort && $outPort->getVariableValue() !== null) {
                // only input value is assigned... this means it was process before with
                // some other child, so just assign value and return
                $inPort->setVariableValue($outPort->getVariableValue());
                return;
            }
        }

        $variableName = $inPort->getVariable();
        if (empty($variableName)) {
            // variable is either null or empty, this means that we do not have to
            // process it and can safely return
            return;
        }

        // check if variable name is the same in both ports
        if ($outPort !== null && $variableName !== $outPort->getVariable()) {
            throw new ExerciseCompilationException(
                "Malformed tree - variables in corresponding ports ($inPortName, $outPortName) do not matches"
            );
        }

        // get the variable from the correct table
        $variable = $pipelineVariables->get($variableName);
        // something's fishy here... better leave now
        if (!$variable) {
            throw new ExerciseCompilationException("Variable '$variableName' could not be resolved");
        }

        // variable is reference, try to find its value in external variables tables
        $variable = $this->findReferenceIfAny(
            $variable,
            $context->getEnvironmentConfigVariables(),
            $exerciseVariables,
            $params
        );

        // resolve name of the file to the hash if variable is remote file
        $this->resolveRemoteFileHash($variable, $pipelineFiles);

        // set variable to both proper ports in child and parent
        $inPort->setVariableValue($variable);
        if ($outPort !== null) {
            $outPort->setVariableValue($variable);
        }
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
     * @throws ExerciseCompilationException
     */
    private function resolveForOtherNodes(
        MergeTree $mergeTree,
        VariablesTable $exerciseVariables,
        VariablesTable $pipelineVariables,
        array $pipelineFiles,
        CompilationContext $context,
        CompilationParams $params
    ) {
        foreach ($mergeTree->getOtherNodes() as $node) {
            foreach ($node->getBox()->getInputPorts() as $inPortName => $inputPort) {
                $parent = $node->getParent($inPortName);
                $outPortName = $parent === null ? null : $parent->findChildPort($node);
                if ($parent !== null && $outPortName === null) {
                    // I do not like what you got!
                    throw new ExerciseCompilationException(
                        "Malformed tree - node '{$node->getBox()->getName()}' not found in parent '{$parent->getBox()->getName()}'"
                    );
                }

                $this->resolveForVariable(
                    $parent,
                    $node,
                    $inPortName,
                    $outPortName,
                    $exerciseVariables,
                    $pipelineVariables,
                    $pipelineFiles,
                    $context,
                    $params
                );
            }

            foreach ($node->getChildrenByPort() as $outPortName => $children) {
                foreach ($children as $child) {
                    $inPortName = $child->findParentPort($node);
                    if (!$inPortName) {
                        // Oh boy, here we go throwing exceptions again!
                        throw new ExerciseCompilationException(
                            "Malformed tree - node '{$node->getBox()->getName()}' not found in child '{$child->getBox()->getName()}'"
                        );
                    }

                    $this->resolveForVariable(
                        $node,
                        $child,
                        $inPortName,
                        $outPortName,
                        $exerciseVariables,
                        $pipelineVariables,
                        $pipelineFiles,
                        $context,
                        $params
                    );
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
     * @throws ExerciseCompilationException
     */
    public function resolve(
        MergeTree $mergeTree,
        VariablesTable $exerciseVariables,
        VariablesTable $pipelineVariables,
        array $pipelineFiles,
        CompilationContext $context,
        CompilationParams $params
    ) {
        $this->resolveForInputNodes($mergeTree, $exerciseVariables, $pipelineVariables, $context, $params);
        $this->resolveForOtherNodes(
            $mergeTree,
            $exerciseVariables,
            $pipelineVariables,
            $pipelineFiles,
            $context,
            $params
        );
    }
}
