<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\Notifications\FailureResolutionEmailsSender;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Security\ACL\ISubmissionFailurePermissions;
use DateTime;

/**
 * Submission failure report viewing and resolution
 * @LoggedIn
 */
class SubmissionFailuresPresenter extends BasePresenter
{

    /**
     * @var SubmissionFailures
     * @inject
     */
    public $submissionFailures;

    /**
     * @var AssignmentSolutionSubmissions
     * @inject
     */
    public $submissions;

    /**
     * @var ISubmissionFailurePermissions
     * @inject
     */
    public $submissionFailureAcl;

    /**
     * @var FailureResolutionEmailsSender
     * @inject
     */
    public $failureResolutionEmailsSender;

    public function checkDefault()
    {
        if (!$this->submissionFailureAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * List all submission failures, ever
     * @GET
     */
    public function actionDefault()
    {
        $this->sendSuccessResponse($this->submissionFailures->findAll());
    }

    public function checkUnresolved()
    {
        if (!$this->submissionFailureAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * List all unresolved submission failures
     * @GET
     */
    public function actionUnresolved()
    {
        $this->sendSuccessResponse($this->submissionFailures->findUnresolved());
    }

    public function checkDetail(string $id)
    {
        $failure = $this->submissionFailures->findOrThrow($id);
        if (!$this->submissionFailureAcl->canView($failure)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get details of a failure
     * @GET
     * @param string $id An identifier of the failure
     */
    public function actionDetail(string $id)
    {
        $failure = $this->submissionFailures->findOrThrow($id);
        $this->sendSuccessResponse($failure);
    }

    public function checkResolve(string $id)
    {
        $failure = $this->submissionFailures->findOrThrow($id);
        if (!$this->submissionFailureAcl->canResolve($failure)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Mark a submission failure as resolved
     * @POST
     * @param string $id An identifier of the failure
     * @Param(name="note", type="post", validation="string:0..255", required=false,
     *   description="Brief description of how the failure was resolved")
     * @Param(name="sendEmail", type="post", validation="bool",
     *        description="True if email should be sent to the author of submission")
     */
    public function actionResolve(string $id)
    {
        $failure = $this->submissionFailures->findOrThrow($id);
        $req = $this->getRequest();

        $failure->resolve($req->getPost("note") ?: "", new DateTime());
        $this->submissionFailures->persist($failure);
        if ($req->getPost("sendEmail")) {
            $this->failureResolutionEmailsSender->failureResolved($failure);
        }
        $this->sendSuccessResponse($failure);
    }
}
