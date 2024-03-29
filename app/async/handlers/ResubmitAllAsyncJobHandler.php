<?php

namespace App\Async\Handler;

use App\Model\Entity\AsyncJob;
use App\Model\Entity\User;
use App\Model\Entity\Assignment;
use App\Async\IAsyncJobHandler;
use App\Async\Dispatcher;
use App\Helpers\SubmissionHelper;
use InvalidArgumentException;

/**
 * Resubmits all submissions of an assignment for re-evaluation.
 * That might be costly if there are many submissions (new job config must be compiled for each one).
 */
class ResubmitAllAsyncJobHandler implements IAsyncJobHandler
{
    public const ID = 'resubmitAll';

    /** @var bool */
    private $canceled = false;

    /** @var SubmissionHelper */
    private $submissionHelper;

    public function __construct(SubmissionHelper $submissionHelper)
    {
        $this->submissionHelper = $submissionHelper;
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
        $this->canceled = false;

        $assignment = $job->getAssociatedAssignment();
        if (!$assignment) {
            throw new InvalidArgumentException("Resubmit all async job is not attached to any assignment.");
        }

        if (!$job->getCreatedBy()) {
            throw new InvalidArgumentException("Resubmit all async job does not have associated the creator user.");
        }

        foreach ($assignment->getAssignmentSolutions() as $solution) {
            /* @phpstan-ignore-next-line */
            if ($this->canceled) { // this line is reported as always false, but cancel may be invoked in signal handler
                return;
            }
            $this->submissionHelper->submit($solution, $job->getCreatedBy(), false); // false = not debug
        }
    }

    /**
     * Factory method for async job entity that will be handled by this handler.
     * @param Dispatcher $dispatcher used to schedule the job
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
