<?php

namespace App\Helpers\Evaluation;

use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseScoreConfig;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Entity\ExerciseLimits;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\RuntimeEnvironment;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;

/**
 * Interface defining operations which exercise and its instance (assignment)
 * should comply. It includes methods needed for compiling exercise
 * configuration to backend format and also methods for proper evaluation.
 */
interface IExercise
{
    /**
     * All hardware groups associated with exercise.
     * @return Collection
     */
    public function getHardwareGroups(): Collection;

    /**
     * Get all runtime environments associated with the exercise
     * @return ReadableCollection
     */
    public function getRuntimeEnvironments(): ReadableCollection;

    /**
     * Get tests which belongs to exercise.
     * @return Collection
     */
    public function getExerciseTests(): Collection;

    /**
     * Get configuration belonging to this exercise.
     * @return ExerciseConfig|null
     */
    public function getExerciseConfig(): ?ExerciseConfig;

    /**
     * Get collection of environment configs belonging to exercise.
     * @return Collection
     */
    public function getExerciseEnvironmentConfigs(): Collection;

    /**
     * Based on runtime environment get corresponding environment configuration.
     * @param RuntimeEnvironment $environment
     * @return ExerciseEnvironmentConfig|null
     */
    public function getExerciseEnvironmentConfigByEnvironment(RuntimeEnvironment $environment): ?ExerciseEnvironmentConfig;

    /**
     * Get collection of limits belonging to exercise.
     * @return Collection
     */
    public function getExerciseLimits(): Collection;

    /**
     * Get limits configuration entity based on environment and hardware group.
     * @param RuntimeEnvironment $environment
     * @param HardwareGroup $hwGroup
     * @return ExerciseLimits|null
     */
    public function getLimitsByEnvironmentAndHwGroup(RuntimeEnvironment $environment, HardwareGroup $hwGroup): ?ExerciseLimits;

    /**
     * Get score configuration entity which holds the calculator type and its configuration.
     * @return ExerciseScoreConfig
     */
    public function getScoreConfig(): ExerciseScoreConfig;

    /**
     * Get an identifier of the configuration type (used by the compiler)
     * @return string
     */
    public function getConfigurationType(): string;

    /**
     * Returns array indexed by the name of the file which contains hash of file.
     * @return string[]
     */
    public function getHashedSupplementaryFiles(): array;

    /**
     * Get tests indexed by entity id and containing actual test name.
     * @return string[]
     */
    public function getExerciseTestsNames(): array;

    /**
     * Get a Collection of localized exercise texts
     */
    public function getLocalizedTexts(): Collection;
}
