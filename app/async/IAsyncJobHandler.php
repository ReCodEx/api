<?php

namespace App\Async;

use App\Model\Entity\AsyncJob;

/**
 * Interface implemented by all asynchronous job handlers.
 */
interface IAsyncJobHandler
{
    /**
     * Get unique identifier of the job class/type.
     * @return string
     */
    public function getId(): string;

    /**
     * Verify arguments of particular job without raising an error.
     * This allows pre-verification when the async job is being saved in database.
     * @param array $args arguments to be verified
     */
    public function checkArgs(array $args): bool;

    /**
     * The main method of the async job does what the job is meant to do.
     * @param AsyncJob $job entity to be executed
     */
    public function execute(AsyncJob $job);

    /**
     * Called by signal handler to terminate the job asap (but gracefully).
     * If the job is not interrupt-able, this function should be implemented with empty body.
     */
    public function cancel(): void;
}
