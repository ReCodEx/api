<?php
namespace App\V1Module\Presenters;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\SubmissionFailure;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Submissions;
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
   * @var Submissions
   * @inject
   */
  public $submissions;

  /**
   * @var ISubmissionFailurePermissions
   * @inject
   */
  public $submissionFailureAcl;

  /**
   * List all submission failures, ever
   * @GET
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
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionListBySubmission(string $submissionId) {
    $submission = $this->submissions->get($submissionId);
    if (!$this->submissionFailureAcl->canViewForSubmission($submission)) {
      throw new ForbiddenRequestException();
    }
    if ($submission === NULL) {
      throw new BadRequestException();
    }

    $this->sendSuccessResponse($this->submissionFailures->findBySubmission($submission));
  }

  /**
   * Get details of a failure
   * @GET
   * @param $id string An identifier of the failure
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $failure = $this->submissionFailures->get($id);
    if ($failure === NULL) {
      throw new BadRequestException();
    }
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
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionResolve(string $id) {
    /** @var SubmissionFailure $failure */
    $failure = $this->submissionFailures->get($id);
    if ($failure === NULL) {
      throw new BadRequestException();
    }

    if (!$this->submissionFailureAcl->canResolve($failure)) {
      throw new ForbiddenRequestException();
    }

    $req = $this->getRequest();

    $failure->resolve($req->getPost("note") ?: "", new DateTime());
    $this->submissionFailures->persist($failure);
    $this->sendSuccessResponse($failure);
  }
}