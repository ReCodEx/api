<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Variable;
use App\Model\Entity\Pipeline as PipelineEntity;

/**
 * Internal pipeline validation service.
 */
class PipelineValidator
{
    /**
     * Validate pipeline.
     * For more detailed description look at @ref App\Helpers\ExerciseConfig\Validator
     * @param PipelineEntity $pipeline
     * @param Pipeline $pipelineConfig
     * @param array|null $pipelineFiles exercise files of pipeline [ fileName => fileHash]
     *                                  if null, the array is automatically loaded from the pipeline entity
     * @throws ExerciseConfigException
     */
    public function validate(PipelineEntity $pipeline, Pipeline $pipelineConfig, ?array $pipelineFiles = null): void
    {
        $variables = $pipelineConfig->getVariablesTable();
        $pipelineFiles = $pipelineFiles ?? $pipeline->getHashedExerciseFiles();

        // Check ports of all boxes
        foreach ($pipelineConfig->getAll() as $box) {
            foreach ($box->getPorts() as $port) {
                if ($port->getVariable() === null || $port->getVariable() === "") {
                    continue; // Empty port - no further validation is necessary
                }

                $variable = $variables->get($port->getVariable());
                if ($variable === null) {
                    throw new ExerciseConfigException(
                        sprintf(
                            "Variable %s used in port %s is not present in the variable table",
                            $port->getVariable(),
                            $port->getName()
                        )
                    );
                }

                if ($variable->getType() !== $port->getType()) {
                    throw new ExerciseConfigException(
                        sprintf(
                            "Port %s of box %s expects a variable of type %s, but supplied variable %s has type %s",
                            $port->getName(),
                            $box->getName(),
                            $port->getType(),
                            $variable->getName(),
                            $variable->getType()
                        )
                    );
                }
            }
        }

        // Check if all variables are written and read by some ports
        /** @var Variable $variable */
        foreach ($variables as $variableName => $variable) {
            $variableUsedAsOutput = false;
            $variableUsedAsInput = false;

            foreach ($pipelineConfig->getAll() as $box) {
                foreach ($box->getOutputPorts() as $outputPort) {
                    if ($outputPort->getVariable() !== $variableName) {
                        continue;
                    }

                    if (!$variableUsedAsOutput) {
                        $variableUsedAsOutput = true;
                        continue;
                    }

                    throw new ExerciseConfigException(
                        sprintf(
                            "Multiple ports output variable %s",
                            $variableName
                        )
                    );
                }

                foreach ($box->getInputPorts() as $inputPort) {
                    if ($inputPort->getVariable() === $variableName) {
                        $variableUsedAsInput = true;
                    }
                }
            }

            if (!$variableUsedAsOutput && $variable->getValue() === "") {
                throw new ExerciseConfigException(sprintf("No port outputs variable %s", $variableName));
            }

            if (!$variableUsedAsInput) {
                throw new ExerciseConfigException(sprintf("No port uses variable %s", $variableName));
            }

            // check exercise remote files if exists in pipeline entity
            ValidationUtils::checkRemoteFilePresence($variable, $pipelineFiles, "pipeline");
        }
    }
}
