<?php

namespace App\Async\Handler;

use App\Model\Entity\AsyncJob;
use App\Model\Entity\User;
use App\Async\IAsyncJobHandler;
use App\Async\Dispatcher;

/**
 * Ping performs no action. It is used by frontend to determine whether the async worker is running.
 */
class PingAsyncJobHandler implements IAsyncJobHandler
{
    public const ID = 'ping';

    public function getId(): string
    {
        return self::ID;
    }

    public function checkArgs(array $args): bool
    {
        return !$args; // no arguments expected
    }

    public function execute(AsyncJob $job)
    {
        // nothing to do
    }

    /**
     * Factory method for async job entity that will be handled by this handler.
     * @param Dispatcher $dispatcher used to schedule the job
     * @param User|null $user creator of the job
     * @return AsyncJob that was just dispatched
     */
    public static function dispatchAsyncJob(Dispatcher $dispatcher, ?User $user): AsyncJob
    {
        $job = new AsyncJob($user, self::ID);
        $dispatcher->schedule($job);
        return $job;
    }

    public function cancel(): void
    {
        // ping is not interrupt-able, nothing to do here
    }
}
