<?php

namespace App\Async\Handler;

use App\Model\Entity\AsyncJob;
use App\Model\Entity\User;
use App\Model\Entity\Assignment;
use App\Async\IAsyncJobHandler;
use App\Async\Dispatcher;
use App\Helpers\Notifications\AssignmentEmailsSender;

/**
 * Scheduled job that sends email notifications when the assignment becomes visible.
 */
class AssignmentNotificationJobHandler implements IAsyncJobHandler
{
    public const ID = 'assignmentNotification';

    /** @var bool */
    private $canceled = false;

    /** @var AssignmentEmailsSender */
    private $assignmentEmailsSender;

    public function __construct(AssignmentEmailsSender $assignmentEmailsSender)
    {
        $this->assignmentEmailsSender = $assignmentEmailsSender;
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
        if ($this->canceled) {
            return;
        }

        $assignment = $job->getAssociatedAssignment();
        if ($assignment) {
            $this->assignmentEmailsSender->assignmentCreated($assignment);
        }
    }

    /**
     * Factory method for async job entity that will be handled by this handler.
     * The job is scheduled at time when the assignment gets visible.
     * @param Dispatcher $dispatcher used to schedule the job
     * @param User $user creator of the job
     * @param Assignment $assignment about which the notification will be sent
     * @return AsyncJob that was just scheduled
     */
    public static function scheduleAsyncJob(Dispatcher $dispatcher, User $user, Assignment $assignment): AsyncJob
    {
        $job = new AsyncJob($user, self::ID, [], $assignment);
        $dispatcher->schedule($job, $assignment->getVisibleFrom());
        return $job;
    }

    public function cancel(): void
    {
        $this->canceled = true;
    }
}
