<?php

namespace App\Async\Handler;

use App\Model\Entity\AsyncJob;
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
}
