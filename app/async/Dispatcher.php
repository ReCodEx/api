<?php

namespace App\Async;

use App\Model\Entity\AsyncJob;
use App\Model\Repository\AsyncJobs;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Utils\Arrays;
use Nette;
use InvalidArgumentException;
use DateTime;
use Exception;

/**
 * Class responsible for scheduling and dispatching async jobs.
 * It cooperates closely with async worker.
 */
class Dispatcher
{
    use Nette\SmartObject;

    /**
     * @var AsyncJobs
     */
    private $asyncJobs;

    /**
     * @var Notify
     */
    private $notify;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var array job type ID => handler (component that executes the job)
     */
    private $knownHandlers = [];

    private $pendingHandler = null;


    public function __construct($config, array $knownHandlers, AsyncJobs $asyncJobs, EntityManagerInterface $em)
    {
        $this->asyncJobs = $asyncJobs;
        $this->entityManager = $em;
        $this->notify = new Notify($config);
        foreach ($knownHandlers as $handler) {
            $this->knownHandlers[$handler->getId()] = $handler;
        }
    }

    /**
     * Schedule execution of an async job. This method may be invoked directly or wrapped in async job component.
     * New job entity is created and saved in DB and the worker is notified.
     * @param AsyncJob $job to be scheduled
     * @param DateTime|null $scheduleAt when the job should be executed (null == immediately)
     * @throws InvalidArgumentException
     */
    public function schedule(AsyncJob $job, DateTime $scheduleAt = null)
    {
        $command = $job->getCommand();
        if (!array_key_exists($command, $this->knownHandlers)) {
            throw new InvalidArgumentException("Unknown async job type '$command'.");
        }
        if ($scheduleAt) {
            $job->setScheduledAt($scheduleAt);
        }
        $this->asyncJobs->persist($job);
        $this->notify->notify(); // this should wake the neighbors
    }

    /**
     * Try to unschedule a job if it has not been executed yet.
     * @param AsyncJob $job to be removed from the scheduling
     * @return bool true if the job was removed, false if it is too late (already running or finished)
     */
    public function unschedule(AsyncJob $job): bool
    {
        if ($job->getStartedAt() !== null) {
            return false; // soft check, that the job has already started
        }

        try {
            $rows = 0;
            $this->entityManager->transactional(function ($em) use ($job, &$rows) {
                $qb = $em->createQueryBuilder();
                $qb->delete('AsyncJob', 'aj')
                    ->where($qb->expr()->isNull("aj.startedAt"))
                    ->andWhere("aj.id = :job")
                    ->setParameter("job", $job);
                $rows = $qb->getQuery()->execute();
            });

            return (bool)$rows;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Actually dispatch the async job to its handler.
     * @param AsyncJob $job entity to be executed
     * @throws InvalidArgumentException
     */
    public function dispatch(AsyncJob $job)
    {
        $command = $job->getCommand();
        if (!array_key_exists($command, $this->knownHandlers)) {
            throw new InvalidArgumentException("Unknown async job type '$command'.");
        }

        // pending handler is set so it can be interrupted by async handler
        $this->pendingHandler = $this->knownHandlers[$command];
        $this->pendingHandler->execute($job);
        $this->pendingHandler = null;
    }

    /**
     * Try to cancel last dispatched job.
     * Called in async signal handler.
     */
    public function cancel()
    {
        if ($this->pendingHandler) {
            $this->pendingHandler->cancel();
        }
    }
}
