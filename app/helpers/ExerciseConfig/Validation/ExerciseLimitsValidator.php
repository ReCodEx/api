<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\EntityMetadata\HwGroupMeta;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Model\Entity\Exercise;
use App\Model\Repository\Pipelines;

/**
 * Internal exercise limits validation service.
 */
class ExerciseLimitsValidator
{
    /**
     * ExerciseConfigValidator constructor.
     */
    public function __construct()
    {
    }


    /**
     * Validate exercise limits.
     * For more detailed description look at @ref App\Helpers\ExerciseConfig\Validator
     * @param Exercise $exercise
     * @param HwGroupMeta $hwGroupMeta
     * @param ExerciseLimits $exerciseLimits
     * @throws ExerciseConfigException
     */
    public function validate(Exercise $exercise, HwGroupMeta $hwGroupMeta, ExerciseLimits $exerciseLimits)
    {
        $exerciseTests = $exercise->getExerciseTestsNames();
        $limits = $exerciseLimits->getLimitsArray();
        foreach ($exerciseTests as $testId => $testName) {
            if (!array_key_exists($testId, $limits)) {
                throw new ExerciseConfigException(
                    sprintf("Test '%s' does not have any limits specified", $testName)
                );
            }
        }

        $cpuTimeSum = 0;
        $wallTimeSum = 0;
        foreach ($limits as $testId => $testLimits) {
            if (!array_key_exists($testId, $exerciseTests)) {
                throw new ExerciseConfigException(
                    sprintf(
                        "Test with id '%s' is not present in the exercise configuration",
                        $testId
                    )
                );
            }

            if ($testLimits->getMemoryLimit() === 0) {
                throw new ExerciseConfigException(sprintf("Test '%s' needs to have a memory limit", $exerciseTests[$testId]));
            }

            if ($testLimits->getCpuTime() === 0.0 && $testLimits->getWallTime() === 0.0) {
                throw new ExerciseConfigException(
                    sprintf("Test '%s' needs to have a time limit (either cpu or wall)", $exerciseTests[$testId])
                );
            }

            $cpuTimeSum += $testLimits->getCpuTime();
            $wallTimeSum += $testLimits->getWallTime();

            if ($testLimits->getMemoryLimit() > $hwGroupMeta->getMemory()) {
                throw new ExerciseConfigException(
                    sprintf("Test '%s' has exceeded memory limit '%d'", $exerciseTests[$testId], $hwGroupMeta->getMemory())
                );
            }

            if ($testLimits->getCpuTime() > $hwGroupMeta->getCpuTimePerTest()) {
                throw new ExerciseConfigException(
                    sprintf(
                        "Test '%s' has exceeded cpu time limit '%d'",
                        $exerciseTests[$testId],
                        $hwGroupMeta->getCpuTimePerTest()
                    )
                );
            }

            if ($testLimits->getWallTime() > $hwGroupMeta->getWallTimePerTest()) {
                throw new ExerciseConfigException(
                    sprintf(
                        "Test '%s' has exceeded wall time limit '%d'",
                        $exerciseTests[$testId],
                        $hwGroupMeta->getWallTimePerTest()
                    )
                );
            }
        }

        if ($cpuTimeSum > $hwGroupMeta->getCpuTimePerExercise()) {
            throw new ExerciseConfigException(
                "Sum of the CPU time limits exceeds defined limit per exercise '{$hwGroupMeta->getCpuTimePerExercise()}'"
            );
        }

        if ($wallTimeSum > $hwGroupMeta->getWallTimePerExercise()) {
            throw new ExerciseConfigException(
                "Sum of the wall time limits exceeds defined limit per exercise '{$hwGroupMeta->getWallTimePerExercise()}'"
            );
        }
    }
}
