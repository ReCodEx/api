<?php

namespace App\Console;

use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Repository\Assignments;
use App\Model\Repository\Exercises;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command that removes exercise configs (all of them including limits) that are not associated with any
 * exercise or assignment and were created before more than a given amount of days
 */
class CleanupExerciseConfigs extends Command {

  /** @var Exercises */
  private $exercises;

  /** @var Assignments */
  private $assignments;

  /** @var EntityManager */
  private $entityManager;

  public function __construct(Exercises $exercises, Assignments $assignments, EntityManager $entityManager) {
    parent::__construct();
    $this->exercises = $exercises;
    $this->assignments = $assignments;
    $this->entityManager = $entityManager;
  }

  protected function configure() {
    $this->setName('db:cleanup:exercise-configs')->setDescription('Remove unused exercise configs (all of them including limits) that are older than 14 days.');
  }

  /**
   * Delete environment configs and return number of deleted entities.
   * @param DateTime $limit
   * @return int
   */
  private function cleanupEnvironmentConfigs(DateTime $limit): int {
    $usedConfigs = [];

    /** @var Exercise $exercise */
    foreach ($this->exercises->findAllAndIReallyMeanAllOkay() as $exercise) {
      foreach ($exercise->getExerciseEnvironmentConfigs() as $config) {
        $usedConfigs[] = $config->getId();
      }
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAllAndIReallyMeanAllOkay() as $assignment) {
      foreach ($assignment->getExerciseEnvironmentConfigs() as $config) {
        $usedConfigs[] = $config->getId();
      }
    }

    $deleteQuery = $this->entityManager->createQuery('
      DELETE FROM App\Model\Entity\ExerciseEnvironmentConfig c
      WHERE c.createdAt <= :date AND c.id NOT IN (:ids)
    ');

    $deleteQuery->setParameter(":date", $limit);
    $deleteQuery->setParameter("ids", $usedConfigs, Connection::PARAM_STR_ARRAY);
    return $deleteQuery->execute();
  }

  /**
   * Delete exercise configs and return number of deleted entities.
   * @param DateTime $limit
   * @return int
   */
  private function cleanupExerciseConfigs(DateTime $limit): int {
    $usedConfigs = [];

    /** @var Exercise $exercise */
    foreach ($this->exercises->findAllAndIReallyMeanAllOkay() as $exercise) {
      $usedConfigs[] = $exercise->getExerciseConfig()->getId();
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAllAndIReallyMeanAllOkay() as $assignment) {
      $usedConfigs[] = $assignment->getExerciseConfig()->getId();
    }

    $deleteQuery = $this->entityManager->createQuery('
      DELETE FROM App\Model\Entity\ExerciseConfig c
      WHERE c.createdAt <= :date AND c.id NOT IN (:ids)
    ');

    $deleteQuery->setParameter(":date", $limit);
    $deleteQuery->setParameter("ids", $usedConfigs, Connection::PARAM_STR_ARRAY);
    return $deleteQuery->execute();
  }

  /**
   * Delete exercise limits and return number of deleted entities.
   * @param DateTime $limit
   * @return int
   */
  private function cleanupLimits(DateTime $limit): int {
    $usedLimits = [];

    /** @var Exercise $exercise */
    foreach ($this->exercises->findAllAndIReallyMeanAllOkay() as $exercise) {
      foreach ($exercise->getExerciseLimits() as $limits) {
        $usedLimits[] = $limits->getId();
      }
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAllAndIReallyMeanAllOkay() as $assignment) {
      foreach ($assignment->getExerciseLimits() as $limits) {
        $usedLimits[] = $limits->getId();
      }
    }

    $deleteQuery = $this->entityManager->createQuery('
      DELETE FROM App\Model\Entity\ExerciseLimits l
      WHERE l.createdAt <= :date AND l.id NOT IN (:ids)
    ');

    $deleteQuery->setParameter(":date", $limit);
    $deleteQuery->setParameter("ids", $usedLimits, Connection::PARAM_STR_ARRAY);
    return $deleteQuery->execute();
  }

  /**
   * Delete tests and return number of deleted entities.
   * @param DateTime $limit
   * @return int
   */
  private function cleanupTests(DateTime $limit): int {
    $usedTests = [];

    /** @var Exercise $exercise */
    foreach ($this->exercises->findAllAndIReallyMeanAllOkay() as $exercise) {
      foreach ($exercise->getExerciseTestsIds() as $id) {
        $usedTests[$id] = true;
      }
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAllAndIReallyMeanAllOkay() as $assignment) {
      foreach ($assignment->getExerciseTestsIds() as $id) {
        $usedTests[$id] = true;
      }
    }

    $deleteQuery = $this->entityManager->createQuery('
      DELETE FROM App\Model\Entity\ExerciseTest t
      WHERE t.createdAt <= :date AND t.id NOT IN (:ids)
    ');

    $deleteQuery->setParameter(":date", $limit);
    $deleteQuery->setParameter("ids", array_keys($usedTests), Connection::PARAM_STR_ARRAY);
    return $deleteQuery->execute();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $now = new DateTime();
    $limit = clone $now;
    $limit->modify("-14 days");

    $this->exercises->beginTransaction();
    try {
      $deletedEnvsCount = $this->cleanupEnvironmentConfigs($limit);
      $deletedConfsCount = $this->cleanupExerciseConfigs($limit);
      $deletedLimsCount = $this->cleanupLimits($limit);
      $deletedTestsCount = $this->cleanupTests($limit);
      $this->exercises->commit();
    }
    catch (\Exception $e) {
      $this->exercises->rollBack();
      throw $e;
    }

    $output->writeln("Removed: {$deletedEnvsCount} environment configs; {$deletedConfsCount} exercise configs; {$deletedLimsCount} exercise limits; {$deletedTestsCount} exercise tests");
    return 0;
  }
}
