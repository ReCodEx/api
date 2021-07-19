<?php

namespace App\Model\Repository;

use App\Model\Entity\AsyncJob;
use App\Model\Entity\Assignment;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use DateTime;

/**
 * @extends BaseRepository<AsyncJob>
 */
class AsyncJobs extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, AsyncJob::class);
    }

    /**
     * Get pending (i.e., not terminated) jobs that fits given criteria.
     * @param string|null $command only jobs of particular command are retrieved
     * @param bool $includeScheduled if true, scheduled jobs are also considered pending jobs
     * @param User|null $createdBy if set, only jobs created by a particular user are retrieved
     * @param Assignment|null $assignment if set, only jobs associated with particular assignment are retireved
     * @return AsyncJob[]
     */
    public function findPendingJobs(
        string $command = null,
        bool $includeScheduled = true,
        User $createdBy = null,
        Assignment $assignment = null
    ): array {
        $qb = $this->repository->createQueryBuilder("j");
        $qb->andWhere($qb->expr()->isNull("j.finishedAt"));

        if ($command) {
            $qb->andWhere($qb->expr()->eq("j.command", ':command'))->setParameter("command", $command);
        }

        if (!$includeScheduled) {
            $qb->andWhere($qb->expr()->isNull("j.scheduledAt"));
        }

        if ($createdBy) {
            $qb->andWhere($qb->expr()->eq("j.createdBy", ':createdBy'))
                ->setParameter("createdBy", $createdBy->getId());
        }

        if ($assignment) {
            $qb->andWhere($qb->expr()->eq("j.associatedAssignment", ':assignment'))
                ->setParameter("assignment", $assignment->getId());
        }

        $qb->addOrderBy('j.createdAt', 'DESC');
        return $qb->getQuery()->getResult();
    }

    /**
     * Get recently failed jobs that fits given criteria.
     * @param string|null $command only jobs of particular command are retrieved
     * @param User|null $createdBy if set, only jobs created by a particular user are retrieved
     * @param Assignment|null $assignment if set, only jobs associated with particular assignment are retireved
     * @return AsyncJob[]
     */
    public function findFailedJobs(
        string $command = null,
        User $createdBy = null,
        Assignment $assignment = null
    ): array {
        $qb = $this->repository->createQueryBuilder("j");
        $qb->andWhere($qb->expr()->isNotNull("j.finishedAt"))->andWhere($qb->expr()->isNotNull("j.error"));

        if ($command) {
            $qb->andWhere($qb->expr()->eq("j.command", ':command'))->setParameter("command", $command);
        }

        if ($createdBy) {
            $qb->andWhere($qb->expr()->eq("j.createdBy", ':createdBy'))
                ->setParameter("createdBy", $createdBy->getId());
        }

        if ($assignment) {
            $qb->andWhere($qb->expr()->eq("j.associatedAssignment", ':assignment'))
                ->setParameter("assignment", $assignment->getId());
        }

        $qb->addOrderBy('j.finishedAt', 'DESC')->addOrderBy('j.createdAt', 'DESC');
        return $qb->getQuery()->getResult();
    }

    /**
     * Find all jobs that needs to be executed.
     * @param int $schedulingWindow size of the scheduling window in seconds
     *            (scheduled jobs too far in the future will not be returned)
     * @param string|null $workerId ID of a worker that requests these jobs
     *                    (so not only fresh jobs, but also retries can be fetched)
     * @return AsyncJob[]
     */
    public function findAllReadyForExecution(int $schedulingWindow, string $workerId = null): array
    {
        $qb = $this->repository->createQueryBuilder("j");

        // jobs either not assigned, or assigned to particular worker
        $workerWhere = $qb->expr()->orX();
        $workerWhere->add($qb->expr()->isNull("j.workerId"));
        if ($workerId) {
            $workerWhere->add($qb->expr()->eq("j.workerId", ':workerId'));
            $qb->setParameter("workerId", $workerId);
        }

        // jobs without scheduling or scheduled in the current window
        $scheduledAt = new DateTime();
        $scheduledAt->modify("+$schedulingWindow seconds");
        $scheduleWhere = $qb->expr()->orX(
            $qb->expr()->lte("j.scheduledAt", ":scheduledAt"),
            $qb->expr()->isNull("j.scheduledAt")
        );
        $qb->setParameter("scheduledAt", $scheduledAt);

        // assemble all where clauses
        $qb->andWhere($qb->expr()->isNull("j.finishedAt"))
            ->andWhere($workerWhere)
            ->andWhere($scheduleWhere);

        // presort them in the most likely order the jobs should be processed
        $qb->addOrderBy('j.retries')->addOrderBy('j.createdAt');

        return $qb->getQuery()->getResult();
    }
}
