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
class SubmissionFailuresPresenter extends BasePresenter {

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

  /**
   * List all submission failures, ever
   * @GET
   * @throws ForbiddenRequestException
   */
  public function actionDefault() {
    if (!$this->submissionFailureAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($this->submissionFailures->findAll());
  }

  /**
   * List all unresolved submission failures
   * @GET
   * @throws ForbiddenRequestException
   */
  public function actionUnresolved() {
    if (!$this->submissionFailureAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($this->submissionFailures->findUnresolved());
  }

  /**
   * List all failures of a single submission
   * @GET
   * @param $submissionId string An identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionListBySubmission(string $submissionId) {
    $submission = $this->submissions->findOrThrow($submissionId);
    if (!$this->submissionFailureAcl->canViewForAssignmentSolutionSubmission($submission)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($this->submissionFailures->findBySubmission($submission));
  }

  /**
   * Get details of a failure
   * @GET
   * @param $id string An identifier of the failure
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $failure = $this->submissionFailures->findOrThrow($id);
    if (!$this->submissionFailureAcl->canView($failure)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($failure);
  }

  /**
   * Mark a submission failure as resolved
   * @POST
   * @Param(name="note", type="post", validation="string:0..255", required=false,
   *   description="Brief description of how the failure was resolved")
   * @param $id string An identifier of the failure
   * @throws ForbiddenRequestException
   */
  public function actionResolve(string $id) {
    $failure = $this->submissionFailures->findOrThrow($id);
    if (!$this->submissionFailureAcl->canResolve($failure)) {
      throw new ForbiddenRequestException();
    }

    $req = $this->getRequest();

    $failure->resolve($req->getPost("note") ?: "", new DateTime());
    $this->submissionFailures->persist($failure);
    $this->failureResolutionEmailsSender->failureResolved($failure);
    $this->sendSuccessResponse($failure);
  }
}
