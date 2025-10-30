<?php

namespace App\Console;

use App\Helpers\Notifications\AsyncJobsStuckEmailsSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Utils\Arrays;
use DateTime;

#[AsCommand(
    name: 'asyncJobs:upkeep',
    description: 'Performs periodic upkeep for async jobs (cleanup, send warning emails)'
)]
class AsyncJobsUpkeep extends Command
{
    /** @var AsyncJobsStuckEmailsSender */
    private $sender;

    /** @var string */
    private $period;

    /** @var string */
    private $cleanupThreshold;

    /** @var string */
    private $cleanupFailedThreshold;

    /** @var string */
    private $stuckThreshold;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(
        array $params,
        AsyncJobsStuckEmailsSender $sender,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->sender = $sender;
        $this->period = Arrays::get($params, ["period"], null);
        $this->cleanupThreshold = Arrays::get($params, ["cleanupThreshold"], $this->period);
        $this->cleanupFailedThreshold = Arrays::get($params, ["cleanupFailedThreshold"], $this->cleanupThreshold);
        $this->stuckThreshold = Arrays::get($params, ["stuckThreshold"], $this->period);
        $this->entityManager = $entityManager;
    }

    /**
     * Delete all async jobs that match given clause and are passed given threshold.
     * @param string $additionalErrorClause raw SQL WHERE clause fragment
     * @param string $threshold relative date-time expression, how old a job must be to be deleted
     */
    protected function deleteAsyncJobByThreshold(string $additionalErrorClause, string $threshold): void
    {
        if ($threshold) {
            $limit = new DateTime();
            $limit->modify("-$threshold");

            $deleteQuery = $this->entityManager->createQuery(
                "DELETE FROM App\Model\Entity\AsyncJob aj
                WHERE $additionalErrorClause AND aj.finishedAt IS NOT NULL AND aj.finishedAt <= :dateLimit"
            );
            $deleteQuery->setParameter("dateLimit", $limit);
            $deleteQuery->execute();
        }
    }

    /**
     * Remove old completed async jobs.
     */
    protected function cleanupOldCompleted(): void
    {
        // successful jobs and failed jobs are deleted after different periods of time
        $this->deleteAsyncJobByThreshold('aj.error IS NULL', $this->cleanupThreshold);
        $this->deleteAsyncJobByThreshold('aj.error IS NOT NULL', $this->cleanupFailedThreshold);
    }

    /**
     * Send notification emails if there are any stalled jobs.
     */
    protected function sendStuckNotifications()
    {
        if (!$this->stuckThreshold) {
            return; // no threshold, no notifications
        }

        $limit = new DateTime();
        $limit->modify("-$this->stuckThreshold");

        $stuckQuery = $this->entityManager->createQuery(
            'SELECT COUNT(aj.id) AS stuckedCount, MIN(COALESCE(aj.scheduledAt, aj.createdAt)) AS minTime
            FROM App\Model\Entity\AsyncJob aj
            WHERE aj.finishedAt IS NULL AND COALESCE(aj.scheduledAt, aj.createdAt) <= :dateLimit'
        );
        $stuckQuery->setParameter("dateLimit", $limit);
        $stuckResult = $stuckQuery->getSingleResult();

        $count = (int)$stuckResult["stuckedCount"];
        if ($count) {
            $maxDelay = (new DateTime())->diff(new DateTime($stuckResult["minTime"]));
            $this->sender->send($count, $maxDelay);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cleanupOldCompleted();
        $this->sendStuckNotifications();
        return 0;
    }
}
