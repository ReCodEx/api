<?php

namespace App\V1Module\Presenters;

use App\Async\Dispatcher;
use App\Async\Handler\PingAsyncJobHandler;
use App\Model\Repository\Assignments;
use App\Model\Repository\AsyncJobs;
use App\Model\Entity\Assignment;
use App\Model\Entity\AsyncJob;
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

    public function noncheckDefault(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        if (!$this->asyncJobsAcl->canViewDetail($asyncJob)) {
            throw new ForbiddenRequestException("You cannot see details of given async job");
        }
    }

    /**
     * Retrieves details about particular async job.
     * @GET
     * @param string $id job identifier
     * @throws NotFoundException
     */
    public function actionDefault(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckList()
    {
        if (!$this->asyncJobsAcl->canList()) {
            throw new ForbiddenRequestException("You cannot list async jobs");
        }
    }

    /**
     * Retrieves details about async jobs that are either pending or were recently completed.
     * @GET
     * @param int|null $ageThreshold Maximal time since completion (in seconds), null = only pending operations
     * @param bool|null $includeScheduled If true, pending scheduled events will be listed as well
     * @throws BadRequestException
     */
    public function actionList(?int $ageThreshold, ?bool $includeScheduled)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAbort(string $id)
    {
        $asyncJob = $this->asyncJobs->findOrThrow($id);
        if (!$this->asyncJobsAcl->canAbort($asyncJob)) {
            throw new ForbiddenRequestException("You cannot abort selected async job");
        }
    }

    /**
     * Retrieves details about particular async job.
     * @POST
     * @param string $id job identifier
     * @throws NotFoundException
     */
    public function actionAbort(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckPing()
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAssignmentJobs($id)
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
    public function actionAssignmentJobs($id)
    {
        $this->sendSuccessResponse("OK");
    }
}
