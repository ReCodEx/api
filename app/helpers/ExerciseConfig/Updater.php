<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseLimits as ExerciseLimitsEntity;
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
   * Environments for exercise were updated. Execute changes in all appropriate
   * exercise configuration entities.
   * @param Exercise $exercise
   * @param User $user
   * @param bool $flush
   * @throws ExerciseConfigException
   */
  public function environmentsUpdated(Exercise $exercise, User $user, bool $flush = true) {
    $this->updateEnvironmentsInConfig($exercise, $user);
    $this->updateEnvironmentsInLimits($exercise, $user);

    if ($flush) {
      $this->exercises->flush();
    }
  }

  /**
   * Tests in exercise were updated. Apply changes in all appropriate exercise
   * configuration entities.
   * @param Exercise $exercise
   * @param User $user
   * @param bool $flush
   * @throws ExerciseConfigException
   */
  public function testsUpdated(Exercise $exercise, User $user, bool $flush = true) {
    $this->updateTestsInConfig($exercise, $user);
    $this->updateTestsInLimits($exercise, $user);

    if ($flush) {
      $this->exercises->flush();
    }
  }

  /**
   * Hardware groups were updated. Apply changes in all exercise configuration
   * entities which are connected to them.
   * @param Exercise $exercise
   * @param User $user
   * @param bool $flush
   */
  public function hwGroupsUpdated(Exercise $exercise, User $user, bool $flush = true) {
    $this->updateHwGroupsInLimits($exercise, $user);

    if ($flush) {
      $this->exercises->flush();
    }
  }


  /**
   * Needed change of ExerciseConfig after update of environment configurations.
   * @param Exercise $exercise
   * @param User $user
   * @throws ExerciseConfigException
   */
  private function updateEnvironmentsInConfig(Exercise $exercise, User $user) {
    $exerciseEnvironments = $exercise->getRuntimeEnvironmentsIds();
    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());

    // go through new environments config and add potentially new environment to ExerciseConfig
    foreach ($exerciseEnvironments as $environmentId) {
      if (in_array($environmentId, $exerciseConfig->getEnvironments())) {
        continue;
      }

      // add environment on top level and into all tests
      $exerciseConfig->addEnvironment($environmentId);
      foreach ($exerciseConfig->getTests() as $test) {
        $test->addEnvironment($environmentId, new Environment());
      }
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
  }

  /**
   * Needed change of ExerciseLimits after update of environment configurations.
   * Non-existing limits for environment is not problem. So just delete the ones
   * which are not currently bound to exercise and be done.
   * @param Exercise $exercise
   * @param User $user
   */
  private function updateEnvironmentsInLimits(Exercise $exercise, User $user) {
    $exerciseEnvironments = $exercise->getRuntimeEnvironmentsIds();

    foreach ($exercise->getExerciseLimits() as $exerciseLimits) {
      $environmentId = $exerciseLimits->getRuntimeEnvironment()->getId();
      if (in_array($environmentId, $exerciseEnvironments)) {
        continue;
      }

      // environment not found in newly assigned runtime environments
      // so delete associated limits
      $exercise->removeExerciseLimits($exerciseLimits);
    }
  }

  /**
   * Exercise tests were changed, this needs to be propagated into exercise
   * configuration.
   * @param Exercise $exercise
   * @param User $user
   * @throws ExerciseConfigException
   */
  private function updateTestsInConfig(Exercise $exercise, User $user) {
    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    $testNames = $exercise->getExerciseTestsIds();

    // remove old tests
    foreach ($exerciseConfig->getTests() as $name => $test) {
      // test name not found in all newly created or updated tests, terminate it
      if (!in_array($name, $testNames)) {
        $exerciseConfig->removeTest($name);
      }
    }

    // finally write changes into exercise entity
    $configEntity = new ExerciseConfigEntity((string) $exerciseConfig, $user, $exercise->getExerciseConfig());
    $exercise->setExerciseConfig($configEntity);
  }

  /**
   * Exercise tests were changed, this needs to be propagated into exercise
   * limits configuration.
   * @param Exercise $exercise
   * @param User $user
   * @throws ExerciseConfigException
   */
  private function updateTestsInLimits(Exercise $exercise, User $user) {
    $testNames = $exercise->getExerciseTestsIds();

    foreach ($exercise->getExerciseLimits() as $exerciseLimits) {
      $limits = $this->loader->loadExerciseLimits($exerciseLimits->getParsedLimits());

      // remove old tests
      foreach ($limits->getLimitsArray() as $name => $testLimits) {
        // test name not found in all newly created or updated tests, terminate it
        if (!in_array($name, $testNames)) {
          $limits->removeLimits($name);
        }
      }

      // finally write changes into limits and array entity
      $limitsEntity = new ExerciseLimitsEntity($exerciseLimits->getRuntimeEnvironment(),
        $exerciseLimits->getHardwareGroup(), (string)$limits, $user, $exerciseLimits);
      $exercise->removeExerciseLimits($exerciseLimits);
      $exercise->addExerciseLimits($limitsEntity);
    }
  }

  /**
   * Hardware groups for the exercise were changes, we need to delete the deprecated ones.
   * @param Exercise $exercise
   * @param User $user
   */
  private function updateHwGroupsInLimits(Exercise $exercise, User $user) {
    // TODO
  }

}
