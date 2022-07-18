<?php

namespace App\Console;

use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\SolutionEvaluation;
use App\Model\Repository\Exercises;
use DateTime;
use Exception;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command that removes exercise configs (all of them including limits) that are not associated with any
 * exercise or assignment and were created before more than a given amount of days
 */
class CleanupExerciseConfigs extends Command
{
    protected static $defaultName = 'db:cleanup:exercise-configs';

    /** @var Exercises */
    private $exercises;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(Exercises $exercises, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->exercises = $exercises;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this->setName('db:cleanup:exercise-configs')->setDescription(
            'Remove unused exercise configs (all of them including limits) that are older than 14 days.'
        );
    }

    /**
     * Delete environment configs and return number of deleted entities.
     * @param DateTime $limit
     * @return int
     */
    private function cleanupEnvironmentConfigs(DateTime $limit): int
    {
        $idsQuery = $this->entityManager->createQuery(
            'SELECT c.id FROM App\Model\Entity\ExerciseEnvironmentConfig c WHERE c.createdAt <= :date
            AND NOT EXISTS (SELECT e FROM App\Model\Entity\Exercise e WHERE c MEMBER OF e.exerciseEnvironmentConfigs)
            AND NOT EXISTS (SELECT a FROM App\Model\Entity\Assignment a WHERE c MEMBER OF a.exerciseEnvironmentConfigs)'
        );
        $idsQuery->setParameter("date", $limit);
        $ids = $idsQuery->getResult();

        $deleteQuery = $this->entityManager->createQuery(
            'DELETE FROM App\Model\Entity\ExerciseEnvironmentConfig c WHERE c.id IN (:ids)'
        );
        $deleteQuery->setParameter("ids", $ids, Connection::PARAM_STR_ARRAY);
        return $deleteQuery->execute();
    }

    /**
     * Delete exercise configs and return number of deleted entities.
     * @param DateTime $limit
     * @return int
     */
    private function cleanupExerciseConfigs(DateTime $limit): int
    {
        $deleteQuery = $this->entityManager->createQuery(
            'DELETE FROM App\Model\Entity\ExerciseConfig c WHERE c.createdAt <= :date
            AND NOT EXISTS (SELECT e FROM App\Model\Entity\Exercise e WHERE e.exerciseConfig = c.id)
            AND NOT EXISTS (SELECT a FROM App\Model\Entity\Assignment a WHERE a.exerciseConfig = c.id)'
        );
        $deleteQuery->setParameter("date", $limit);
        return $deleteQuery->execute();
    }

    /**
     * Delete exercise score configs and return number of deleted entities.
     * @param DateTime $limit
     * @return int
     */
    private function cleanupScoreConfigs(DateTime $limit): int
    {
        $deleteQuery = $this->entityManager->createQuery(
            'DELETE FROM App\Model\Entity\ExerciseScoreConfig c WHERE c.createdAt <= :date
            AND NOT EXISTS (SELECT e FROM App\Model\Entity\Exercise e WHERE e.scoreConfig = c.id)
            AND NOT EXISTS (SELECT a FROM App\Model\Entity\Assignment a WHERE a.scoreConfig = c.id)
            AND NOT EXISTS (SELECT se FROM App\Model\Entity\SolutionEvaluation se WHERE se.scoreConfig = c.id)'
        );
        $deleteQuery->setParameter("date", $limit);
        return $deleteQuery->execute();
    }

    /**
     * Delete exercise limits and return number of deleted entities.
     * @param DateTime $limit
     * @return int
     */
    private function cleanupLimits(DateTime $limit): int
    {
        $idsQuery = $this->entityManager->createQuery(
            'SELECT l.id FROM App\Model\Entity\ExerciseLimits l WHERE l.createdAt <= :date
            AND NOT EXISTS (SELECT e FROM App\Model\Entity\Exercise e WHERE l MEMBER OF e.exerciseLimits)
            AND NOT EXISTS (SELECT a FROM App\Model\Entity\Assignment a WHERE l MEMBER OF a.exerciseLimits)'
        );
        $idsQuery->setParameter("date", $limit);
        $ids = $idsQuery->getResult();

        $deleteQuery = $this->entityManager->createQuery(
            'DELETE FROM App\Model\Entity\ExerciseLimits l WHERE l.id IN (:ids)'
        );
        $deleteQuery->setParameter("ids", $ids, Connection::PARAM_STR_ARRAY);
        return $deleteQuery->execute();
    }

    /**
     * Delete tests and return number of deleted entities.
     * @param DateTime $limit
     * @return int
     */
    private function cleanupTests(DateTime $limit): int
    {
        $idsQuery = $this->entityManager->createQuery(
            'SELECT t.id FROM App\Model\Entity\ExerciseTest t WHERE t.createdAt <= :date
            AND NOT EXISTS (SELECT e FROM App\Model\Entity\Exercise e WHERE t MEMBER OF e.exerciseTests)
            AND NOT EXISTS (SELECT a FROM App\Model\Entity\Assignment a WHERE t MEMBER OF a.exerciseTests)'
        );
        $idsQuery->setParameter("date", $limit);
        $ids = $idsQuery->getResult();

        $deleteQuery = $this->entityManager->createQuery(
            'DELETE FROM App\Model\Entity\ExerciseTest t WHERE t.id IN (:ids)'
        );
        $deleteQuery->setParameter("ids", $ids, Connection::PARAM_STR_ARRAY);
        return $deleteQuery->execute();
    }


    protected function executeUnsafe(DateTime $limit, OutputInterface $output)
    {
        $toDelete = [
            'EnvironmentConfigs',
            'ExerciseConfigs',
            'ScoreConfigs',
            'Limits',
            'Tests',
        ];

        $report = [ 'Removed:' ];
        foreach ($toDelete as $key) {
            $method = "cleanup$key";
            $deletedCount = $this->$method($limit);
            $report[] = "$key($deletedCount)";
        }
        $this->exercises->commit();
        $output->writeln(join(' ', $report));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = new DateTime();
        $limit->modify("-14 days");

        $tryAgain = 5;
        while ($tryAgain-- > 0) {
            $exception = null;
            $this->exercises->beginTransaction();
            try {
                $this->executeUnsafe($limit, $output);
                break;
            } catch (Exception $e) {
                $this->exercises->rollBack();
                $exception = $e;
            }
        }

        if (!empty($exception)) {
            throw $exception; // re-throw last exception that caused DB TX failure
        }

        return 0;
    }
}
