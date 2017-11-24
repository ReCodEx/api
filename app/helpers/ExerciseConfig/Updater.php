<?php

namespace App\Helpers\ExerciseConfig;

use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTest;
use App\Model\Entity\User;
use App\Model\Repository\Exercises;
use App\Model\Entity\ExerciseConfig as ExerciseConfigEntity;


/**
 * Exercise configuration is consisting of multiple parts and entities. These
 * parts are handled by separate endpoints. This means that changes made in one
 * endpoint might have consequences in another part of configuration. Updater
 * service handles this cases and updates corresponding modules of
 * configuration.
 */
class Updater {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * @var Exercises
   */
  private $exercises;

  /**
   * Updater constructor.
   * @param Loader $loader
   * @param Exercises $exercises
   */
  public function __construct(Loader $loader, Exercises $exercises) {
    $this->loader = $loader;
    $this->exercises = $exercises;
  }


  /**
   * Needed change of ExerciseConfig after update of environment configurations.
   * @param Exercise $exercise
   * @param User $user
   * @param bool $flush
   */
  public function updateEnvironmentsInExerciseConfig(Exercise $exercise, User $user, bool $flush = false) {
    $exerciseEnvironments = $exercise->getRuntimeEnvironmentsIds();
    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());

    // go through new environments config and add potentially new environment to ExerciseConfig
    foreach ($exerciseEnvironments as $environmentId) {
      if (in_array($environmentId, $exerciseConfig->getEnvironments())) {
        continue;
      }

      // environment can be added only at the top level, in the tests there
      // should be assigned default pipeline values during transformation
      $exerciseConfig->addEnvironment($environmentId);
    }

    // delete unused environments from ExerciseConfig
    foreach ($exerciseConfig->getEnvironments() as $environmentId) {
      if (in_array($environmentId, $exerciseEnvironments)) {
        continue;
      }

      // environment needs to be deleted from top level, but also all tests
      // have to be run through and optionally environments should be deleted
      $exerciseConfig->removeEnvironment($environmentId);
      foreach ($exerciseConfig->getTests() as $test) {
        $test->removeEnvironment($environmentId);
      }
    }

    // finally write changes into exercise entity
    $configEntity = new ExerciseConfigEntity((string) $exerciseConfig, $user, $exercise->getExerciseConfig());
    $exercise->setExerciseConfig($configEntity);

    if ($flush) {
      $this->exercises->flush();
    }
  }

  /**
   * Exercise tests were changed, this needs to be propagated into exercise
   * configuration.
   * @param Exercise $exercise
   * @param User $user
   * @param bool $flush
   */
  public function updateTestsInExerciseConfig(Exercise $exercise, User $user, bool $flush = false) {
    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    $testNames = $exercise->getExerciseTests()->map(function (ExerciseTest $test) {
      return $test->getName();
    })->getValues();

    // if new tests were added, add them also to configuration
    foreach ($testNames as $name) {
      if ($exerciseConfig->getTest($name) === null) {
        $exerciseConfig->addTest($name, new Test);
      }
    }

    // go through current tests and find the ones which were deleted
    foreach ($exerciseConfig->getTests() as $name => $test) {
      if (array_search($name, $testNames) === false) {
        $exerciseConfig->removeTest($name);
      }
    }

    // finally write changes into exercise entity
    $configEntity = new ExerciseConfigEntity((string) $exerciseConfig, $user, $exercise->getExerciseConfig());
    $exercise->setExerciseConfig($configEntity);

    if ($flush) {
      $this->exercises->flush();
    }
  }

}
