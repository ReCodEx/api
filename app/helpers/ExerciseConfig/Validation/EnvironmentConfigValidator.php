<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Entity\Exercise;

/**
 * Internal environment configuration validation service.
 */
class EnvironmentConfigValidator
{

    /**
     * Validate exercise environment configuration.
     * For more detailed description look at @ref App\Helpers\ExerciseConfig\Validator
     * @param Exercise $exercise
     * @param VariablesTable $table
     * @throws ExerciseConfigException
     */
    public function validate(Exercise $exercise, VariablesTable $table)
    {
        $exerciseFiles = $exercise->getHashedSupplementaryFiles();
        foreach ($table->getAll() as $variable) {
            ValidationUtils::checkRemoteFilePresence($variable, $exerciseFiles, "exercise");
        }
    }
}
