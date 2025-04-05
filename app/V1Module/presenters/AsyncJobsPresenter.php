<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Async\Dispatcher;
use App\Async\Handler\PingAsyncJobHandler;
use App\Model\Repository\Assignments;
use App\Model\Repository\AsyncJobs;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IAsyncJobPermissions;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use Doctrine\Common\Collections\Criteria;
use Exception;
use DateTime;

/**
 * Basic management of asynchronous jobs executed by core systemd service.
 * Async jobs are jobs that might take a long time, so they cannot be executed in request handler;
 * however, they need to access functions of the core API module.
 */
class AsyncJobsPresenter extends BasePresenter
{
    /**
     * @var Dispatcher
     * @inject
     */
    public $dispatcher;

    /**
     * @var Assignments
     * @inject
     */
    public $assignments;

    /**
     * @var AsyncJobs
     * @inject
     */
    public $asyncJobs;

    /**
     * @var IAsyncJobPermissions
     * @inject
     */
    public $asyncJobsAcl;

    /**
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentsAcl;

    public function checkDefault(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        if (!$this->asyncJobsAcl->canViewDetail($asyncJob)) {
            throw new ForbiddenRequestException("You cannot see details of given async job");
        }
    }

    /**
     * Retrieves details about particular async job.
     * @GET
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "job identifier", required: true)]
    public function actionDefault(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        $this->sendSuccessResponse($asyncJob);
    }

    public function checkList()
    {
        if (!$this->asyncJobsAcl->canList()) {
            throw new ForbiddenRequestException("You cannot list async jobs");
        }
    }

    /**
     * Retrieves details about async jobs that are either pending or were recently completed.
     * @GET
     * @throws BadRequestException
     */
    #[Query(
        "ageThreshold",
        new VInt(),
        "Maximal time since completion (in seconds), null = only pending operations",
        required: false,
        nullable: true,
    )]
    #[Query(
        "includeScheduled",
        new VBool(),
        "If true, pending scheduled events will be listed as well",
        required: false,
        nullable: true,
    )]
    public function actionList(?int $ageThreshold, ?bool $includeScheduled)
    {
        if ($ageThreshold && $ageThreshold < 0) {
            throw new BadRequestException("Age threshold must not be negative.");
        }

        // criteria for termination (either pending or within threshold)
        $finishedAt = Criteria::expr()->eq('finishedAt', null);
        if ($ageThreshold) {
            $thresholdDate = new DateTime();
            $thresholdDate->modify("-$ageThreshold seconds");
            $finishedAt = Criteria::expr()->orX(
                $finishedAt,
                Criteria::expr()->gte('finishedAt', $thresholdDate)
            );
        }

        $criteria = Criteria::create()->where(
            $includeScheduled
                ? $finishedAt
                : Criteria::expr()->andX(
                    $finishedAt,
                    Criteria::expr()->eq('scheduledAt', null)
                )
        );
        $criteria->orderBy(['createdAt' => 'ASC']);
        $jobs = $this->asyncJobs->matching($criteria)->toArray();

        $jobs = array_filter($jobs, function ($job) {
            return $this->asyncJobsAcl->canViewDetail($job);
        });

        $this->sendSuccessResponse($jobs);
    }

    public function checkAbort(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        if (!$this->asyncJobsAcl->canAbort($asyncJob)) {
            throw new ForbiddenRequestException("You cannot abort selected async job");
        }
    }

    /**
     * Retrieves details about particular async job.
     * @POST
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "job identifier", required: true)]
    public function actionAbort(string $id)
    {
        $this->asyncJobs->beginTransaction();
        try {
            $asyncJob = $this->asyncJobs->findOrThrow($id);
            if ($asyncJob->getStartedAt() === null && $asyncJob->getFinishedAt() === null) {
                // if the job has not been started yet, it can be aborted
                $asyncJob->setFinishedNow();
                $asyncJob->appendError("ABORTED");
                $this->asyncJobs->persist($asyncJob);
                $this->asyncJobs->commit();
            } else {
                $this->asyncJobs->rollback();
            }
        } catch (Exception $e) {
            $this->asyncJobs->rollback();
            throw $e;
        }

        $this->sendSuccessResponse($asyncJob);
    }

    public function checkPing()
    {
        if (!$this->asyncJobsAcl->canPing()) {
            throw new ForbiddenRequestException("You cannot ping async job worker");
        }
    }

    /**
     * Initiates ping job. An empty job designed to verify the async handler is running.
     * @POST
     */
    public function actionPing()
    {
        $asyncJob = PingAsyncJobHandler::dispatchAsyncJob($this->dispatcher, $this->getCurrentUser());
        $this->sendSuccessResponse($asyncJob);
    }

    public function checkAssignmentJobs($id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentsAcl->canViewAssignmentAsyncJobs($assignment)) {
            throw new ForbiddenRequestException("You cannot list async jobs of given assignment");
        }
    }

    /**
     * Get all pending async jobs related to a particular assignment.
     * @GET
     */
    #[Path("id", new VUuid(), required: true)]
    public function actionAssignmentJobs($id)
    {
        $asyncJobs = $this->asyncJobs->findAssignmentJobs($id);
        $this->sendSuccessResponse($asyncJobs);
    }
}
