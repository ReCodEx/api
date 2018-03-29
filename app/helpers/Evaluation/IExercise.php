<?php

namespace App\Helpers\Evaluation;

use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Entity\ExerciseLimits;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\RuntimeEnvironment;
use Doctrine\Common\Collections\Collection;


/**
 * Interface defining operations which exercise and its instance (assignment)
 * should comply. It includes methods needed for compiling exercise
 * configuration to backend format and also methods for proper evaluation.
 */
interface IExercise {

  /**
   * All hardware groups associated with exercise.
   * @return Collection
   */
  function getHardwareGroups(): Collection;

  /**
   * Get all runtime environments associated with the exercise
   * @return Collection
   */
  function getRuntimeEnvironments(): Collection;

  /**
   * Get tests which belongs to exercise.
   * @return Collection
   */
  function getExerciseTests(): Collection;

  /**
   * Get configuration belonging to this exercise.
   * @return ExerciseConfig|null
   */
  function getExerciseConfig(): ?ExerciseConfig;

  /**
   * Get collection of environment configs belonging to exercise.
   * @return Collection
   */
  function getExerciseEnvironmentConfigs(): Collection;

  /**
   * Based on runtime environment get corresponding environment configuration.
   * @param RuntimeEnvironment $environment
   * @return ExerciseEnvironmentConfig|null
   */
  function getExerciseEnvironmentConfigByEnvironment(RuntimeEnvironment $environment): ?ExerciseEnvironmentConfig;

  /**
   * Get collection of limits belonging to exercise.
   * @return Collection
   */
  function getExerciseLimits(): Collection;

  /**
   * Get limits configuration entity based on environment and hardware group.
   * @param RuntimeEnvironment $environment
   * @param HardwareGroup $hwGroup
   * @return ExerciseLimits|null
   */
  function getLimitsByEnvironmentAndHwGroup(RuntimeEnvironment $environment, HardwareGroup $hwGroup): ?ExerciseLimits;

  /**
   * Get score calculator specific for this exercise.
   * @return null|string
   */
  function getScoreCalculator(): ?string;

  /**
   * Get score configuration which will be used within exercise calculator.
   * @return string
   */
  function getScoreConfig(): string;

  /**
   * Get an identifier of the configuration type (used by the compiler)
   * @return string
   */
  function getConfigurationType(): string;

  /**
   * Returns array indexed by the name of the file which contains hash of file.
   * @return string[]
   */
  function getHashedSupplementaryFiles(): array;

  /**
   * Get tests indexed by entity id and containing actual test name.
   * @return string[]
   */
  function getExerciseTestsNames(): array;

  /**
   * Get a Collection of localized exercise texts
   */
  function getLocalizedTexts(): Collection;
}
