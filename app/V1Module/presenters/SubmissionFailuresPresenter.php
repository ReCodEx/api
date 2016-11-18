<?php
namespace App\V1Module\Presenters;
use App\Exceptions\BadRequestException;
use App\Model\Entity\SubmissionFailure;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Submissions;
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
   * List all submission failures, ever
   * @GET
   * @UserIsAllowed(submissionFailures="view-all")
   */
  public function actionDefault() {
    $this->sendSuccessResponse($this->submissionFailures->findAll());
  }

  /**
   * List all unresolved submission failures
   * @GET
   * @UserIsAllowed(submissionFailures="view-all")
   */
  public function actionUnresolved() {
    $this->sendSuccessResponse($this->submissionFailures->findUnresolved());
  }

  /**
   * List all failures of a single submission
   * @GET
   * @UserIsAllowed(submissionFailures="view-submission")
   * @param $submissionId string An identifier of the submission
   * @throws BadRequestException
   */
  public function actionListBySubmission(string $submissionId) {
    $submission = $this->submissions->get($submissionId);
    if ($submission === NULL) {
      throw new BadRequestException();
    }

    $this->sendSuccessResponse($this->submissionFailures->findBySubmission($submission));
  }

  /**
   * Get details of a failure
   * @GET
   * @UserIsAllowed(submissionFailures="view")
   * @param $id string An identifier of the failure
   * @throws BadRequestException
   */
  public function actionDetail(string $id) {
    $failure = $this->submissionFailures->get($id);
    if ($failure === NULL) {
      throw new BadRequestException();
    }

    $this->sendSuccessResponse($failure);
  }

  /**
   * Mark a submission failure as resolved
   * @POST
   * @Param(name="note", type="post", validation="string:0..255", required=false,
   *   description="Brief description of how the failure was resolved")
   * @param $id string An identifier of the failure
   * @UserIsAllowed(submissionFailures="resolve")
   * @throws BadRequestException
   */
  public function actionResolve(string $id) {
    /** @var SubmissionFailure $failure */
    $failure = $this->submissionFailures->get($id);
    if ($failure === NULL) {
      throw new BadRequestException();
    }

    $req = $this->getRequest();

    $failure->resolve($req->getPost("note") ?: "", new DateTime());
    $this->submissionFailures->persist($failure);
    $this->sendSuccessResponse($failure);
  }
}