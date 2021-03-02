<?php

namespace App\Async;

use App\Model\Entity\AsyncJob;
use Nette\Utils\Arrays;
use Nette;

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
     * The main method of the async job does what the job is ment to do.
     * @param AsyncJob $job entity to be executed
     */
    public function execute(AsyncJob $job);
}
