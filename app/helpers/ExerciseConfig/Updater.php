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
   * @param array $newTestNames
   * @param array $replacedTestNames indexed by old name, containing new name
   * @param bool $flush
   * @throws ExerciseConfigException
   */
  public function testsUpdated(Exercise $exercise, User $user,
      array $newTestNames, array $replacedTestNames, bool $flush = true) {
    $this->updateTestsInConfig($exercise, $user, $newTestNames, $replacedTestNames);
    $this->updateTestsInLimits($exercise, $user, $newTestNames, $replacedTestNames);

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
   * @param array $newTestNames
   * @param array $replacedTestNames indexed by old name, containing new name
   * @throws ExerciseConfigException
   */
  private function updateTestsInConfig(Exercise $exercise, User $user,
      array $newTestNames, array $replacedTestNames) {
    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    $testNames = array_merge($newTestNames, $replacedTestNames);

    // add all newly created tests
    foreach ($newTestNames as $newTestName) {
      $exerciseConfig->addTest($newTestName, new Test);
    }

    // replace renamed tests
    foreach ($replacedTestNames as $originalTestName => $replacedTestName) {
      $test = $exerciseConfig->getTest($originalTestName);
      if (!$test) {
        continue;
      }

      $exerciseConfig->removeTest($originalTestName);
      $exerciseConfig->addTest($replacedTestName, $test);
    }

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
   * @param array $newTestNames
   * @param array $replacedTestNames indexed by old name, containing new name
   * @throws ExerciseConfigException
   */
  private function updateTestsInLimits(Exercise $exercise, User $user,
      array $newTestNames, array $replacedTestNames) {
    $testNames = array_merge($newTestNames, $replacedTestNames);

    foreach ($exercise->getExerciseLimits() as $exerciseLimits) {
      $limits = $this->loader->loadExerciseLimits($exerciseLimits->getParsedLimits());

      // replace renamed tests
      foreach ($replacedTestNames as $originalTestName => $replacedTestName) {
        $testLimits = $limits->getLimits($originalTestName);
        if (!$testLimits) {
          continue;
        }

        $limits->removeLimits($originalTestName);
        $limits->addLimits($replacedTestName, $testLimits);
      }

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

}
