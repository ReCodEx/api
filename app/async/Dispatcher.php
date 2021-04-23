<?php

namespace App\Async;

use App\Model\Entity\User;
use App\Model\Entity\AsyncJob;
use App\Model\Repository\AsyncJobs;
use Nette\Utils\Arrays;
use Nette;
use InvalidArgumentException;
use DateTime;

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
     * @var array job type ID => handler (component that executes the job)
     */
    private $knownHandlers = [];

    private $pendingHandler = null;


    public function __construct($config, array $knownHandlers, AsyncJobs $asyncJobs)
    {
        $this->asyncJobs = $asyncJobs;
        $this->notify = new Notify($config);
        foreach ($knownHandlers as $handler) {
            $this->knownHandlers[$handler->getId()] = $handler;
        }
    }

    /**
     * Schedule execution of an async job. This method may be invoked directly or wrapped in async job component.
     * New job entity is created and saved in DB and the worker is notified.
     * @param User $user on whose behalf the job is created
     * @param string $command class name of the job handler
     * @param array $args job arguments
     * @param DateTime|null $scheduleAt when the job should be executed (null == immediately)
     * @throws InvalidArgumentException
     */
    public function schedule(User $user, string $command, array $args = [], DateTime $scheduleAt = null)
    {
        if (!array_key_exists($command, $this->knownHandlers)) {
            throw new InvalidArgumentException("Unknown async job type '$command'.");
        }
        $job = new AsyncJob($user, $command, $args);
        if ($scheduleAt) {
            $job->setScheduledAt($scheduleAt);
        }
        $this->asyncJobs->persist($job);
        $this->notify->notify(); // this should wake the neighbors
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
