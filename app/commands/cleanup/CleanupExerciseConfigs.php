<?php

namespace App\Console;

use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command that removes exercise configs (all of them including limits) that are not associated with any
 * exercise or assignment and were created before more than a given amount of days
 */
class CleanupExerciseConfigs extends Command {

  /** @var EntityRepository */
  private $exercises;

  /** @var EntityRepository */
  private $assignments;

  /** @var EntityManager */
  private $entityManager;

  public function __construct(EntityManager $entityManager) {
    parent::__construct();
    $this->exercises = $entityManager->getRepository(Exercise::class); // even deleted exercises has to be found
    $this->assignments = $entityManager->getRepository(Assignment::class); // even deleted assignments has to be found
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
    foreach ($this->exercises->findAll() as $exercise) {
      foreach ($exercise->getExerciseEnvironmentConfigs() as $config) {
        $usedConfigs[] = $config->getId();
      }
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAll() as $assignment) {
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
    foreach ($this->exercises->findAll() as $exercise) {
      $usedConfigs[] = $exercise->getExerciseConfig()->getId();
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAll() as $assignment) {
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
    foreach ($this->exercises->findAll() as $exercise) {
      foreach ($exercise->getExerciseLimits() as $limits) {
        $usedLimits[] = $limits->getId();
      }
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAll() as $assignment) {
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

  protected function execute(InputInterface $input, OutputInterface $output) {
    $now = new DateTime();
    $limit = clone $now;
    $limit->modify("-14 days");

    $deletedEnv = $this->cleanupEnvironmentConfigs($limit);
    $deletedConf = $this->cleanupExerciseConfigs($limit);
    $deletedLim = $this->cleanupLimits($limit);

    $output->writeln(sprintf("Removed: %d environment configs; %d exercise configs; %d exercise limits", $deletedEnv, $deletedConf, $deletedLim));
    return 0;
  }
}
