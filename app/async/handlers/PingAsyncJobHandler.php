<?php

namespace App\Async\Handler;

use App\Model\Entity\AsyncJob;
use App\Model\Entity\User;
use App\Async\IAsyncJobHandler;

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
     * @param User|null $user creator of the job
     */
    public static function createAsyncJob(?User $user): AsyncJob
    {
        return new AsyncJob($user, PingAsyncJobHandler::ID);
    }

    public function cancel(): void
    {
        // ping is not interruptable, nothing to do here
    }
}
