<?php

namespace App\Async\Handler;

use App\Model\Entity\AsyncJob;
use App\Model\Entity\User;
use App\Model\Entity\Assignment;
use App\Model\Repository\Assignments;
use App\Async\IAsyncJobHandler;
use App\Async\Dispatcher;
use App\Helpers\SubmissionHelper;
use InvalidArgumentException;

/**
 * Scheduled job that sends email notifications when the assignment becomes visible.
 */
class AssignmentNotificationJobHandler implements IAsyncJobHandler
{
    public const ID = 'assignmentNotification';

    /** @var bool */
    private $canceled = false;

    /** @var SubmissionHelper */
    private $submissionHelper;

    /** @var Assignments */
    private $assignments;

    public function __construct(SubmissionHelper $submissionHelper, Assignments $assignments)
    {
        $this->submissionHelper = $submissionHelper;
        $this->assignments = $assignments;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function checkArgs(array $args): bool
    {
        return !$args; // no arguments expected (assignment is attached to the job itself)
    }

    public function execute(AsyncJob $job)
    {
    }

    /**
     * Factory method for async job entity that will be handled by this handler.
     * @param User $user creator of the job
     * @param Assignment $assignment of which all solutions will be resubmitted
     * @return AsyncJob that was just dispatched
     */
    public static function dispatchAsyncJob(Dispatcher $dispatcher, User $user, Assignment $assignment): AsyncJob
    {
        $job = new AsyncJob($user, self::ID, [], $assignment);
        $dispatcher->schedule($job);
        return $job;
    }

    public function cancel(): void
    {
        $this->canceled = true;
    }
}
