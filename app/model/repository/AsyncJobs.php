<?php

namespace App\Model\Repository;

use App\Model\Entity\AsyncJob;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use DateTime;

class AsyncJobs extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, AsyncJob::class);
    }

    /**
     * Find all jobs that needs to be executed.
     * @param int $schedulingWindow size of the scheduling window in seconds
     *            (scheduled jobs too far in the future will not be returned)
     * @param string|null $workerId ID of a worker that requests these jobs
     *                    (so not only fresh jobs, but also retries can be fetched)
     * @return AsyncJob[]
     */
    public function findAllReadyForExecution(int $schedulingWindow, string $workerId = null)
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
        $qb->andWhere($qb->expr()->isNull("j.terminatedAt"))
            ->andWhere($workerWhere)
            ->andWhere($scheduleWhere);

        // presort them in the most likely order the jobs should be processed
        $qb->addOrderBy('j.retries')->addOrderBy('j.createdAt');

        return $qb->getQuery()->getResult();
    }
}
