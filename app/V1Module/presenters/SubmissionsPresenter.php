<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotFoundException;
use App\Helpers\EvaluationLoader;
use App\Helpers\FileServerProxy;
use App\Model\Entity\Group;
use App\Model\Entity\Submission;
use App\Model\Repository\Submissions;
use App\Model\Repository\SolutionEvaluations;
use App\Model\Repository\Users;
use App\Exceptions\ForbiddenRequestException;
use App\Responses\GuzzleResponse;
use App\Security\ACL\ISubmissionPermissions;

/**
 * Endpoints for manipulation of solution submissions
 * @LoggedIn
 */
class SubmissionsPresenter extends BasePresenter {

  /**
   * @var Submissions
   * @inject
   */
  public $submissions;

  /**
   * @var SolutionEvaluations
   * @inject
   */
  public $evaluations;

  /**
   * @var EvaluationLoader
   * @inject
   */
  public $evaluationLoader;

  /**
   * @var Users
   * @inject
   */
  public $users;

  /**
   * @var FileServerProxy
   * @inject
   */
  public $fileServerProxy;

  /**
   * @var ISubmissionPermissions
   * @inject
   */
  public $submissionAcl;

  /**
   * Get a list of all submissions, ever
   * @GET
   */
  public function actionDefault() {
    if (!$this->submissionAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $submissions = array_filter($this->submissions->findAll(), (function (Submission $submission) {
      return $this->submissionAcl->canViewDetail($submission);
    }));

    $this->sendSuccessResponse($submissions);
  }

  /**
   * Get information about the evaluation of a submission
   * @GET
   * @param string $id Identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionEvaluation(string $id) {
    /** @var Submission $submission */
    $submission = $this->submissions->findOrThrow($id);

    if (!$this->submissionAcl->canViewEvaluation($submission)) {
      throw new ForbiddenRequestException("You cannot access this evaluation");
    }

    if (!$submission->hasEvaluation()) { // the evaluation must be loaded first
      $evaluation = $this->evaluationLoader->load($submission);
      if ($evaluation !== NULL) {
        $this->evaluations->persist($evaluation);
        $this->submissions->persist($submission);
      } else {
        // the evaluation is probably not ready yet
        // - display partial information about the submission, do not throw an error
      }
    }

    $canViewDetails = $this->submissionAcl->canViewEvaluationDetails($submission);
    $this->sendSuccessResponse($submission->getData($canViewDetails));
  }

  /**
   * Set new amount of bonus points for a submission
   * @POST
   * @Param(type="post", name="bonusPoints", validation="numericint", description="New amount of bonus points, can be negative number")
   * @param string $id Identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionSetBonusPoints(string $id) {
    $newBonusPoints = $this->getRequest()->getPost("bonusPoints");
    $submission = $this->submissions->findOrThrow($id);
    $evaluation = $submission->getEvaluation();

    if (!$this->submissionAcl->canSetBonusPoints($submission)) {
      throw new ForbiddenRequestException("You cannot change amount of bonus points for this submission");
    }

    $evaluation->setBonusPoints($newBonusPoints);
    $this->evaluations->persist($evaluation);

    $this->sendSuccessResponse("OK");
  }

  /**
   * Set submission of student as accepted, this submission will be then presented as the best one.
   * @GET
   * @param string $id identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionSetAcceptedSubmission(string $id) {
    $submission = $this->submissions->findOrThrow($id);

    if (!$submission->hasEvaluation()) {
      throw new ForbiddenRequestException("Submission does not have evaluation yet");
    }

    if (!$this->submissionAcl->canSetAccepted($submission)) {
      throw new ForbiddenRequestException("You cannot change accepted flag for this submission");
    }

    // accepted flag has to be set to false for all other submissions
    $assignmentSubmissions = $submission->getAssignment()->getValidSubmissions($submission->getUser());
    foreach ($assignmentSubmissions as $assignmentSubmission) {
      $assignmentSubmission->setAccepted(false);
    }

    // finally set the right submission as accepted
    $submission->setAccepted(true);
    $this->submissions->flush();

    /** @var Group $groupOfSubmission */
    $groupOfSubmission = $submission->getAssignment()->getGroup();
    $this->forward('Groups:studentsStats', $groupOfSubmission->getId(), $submission->getUser()->getId());
  }

  /**
   * Download result archive from backend for particular submission.
   * @GET
   * @param string $id
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionDownloadResultArchive(string $id) {
    $submission = $this->submissions->findOrThrow($id);

    if (!$this->submissionAcl->canDownloadResultArchive($submission)) {
      throw new ForbiddenRequestException("You cannot access result archive for this submission");
    }

    if (!$submission->hasEvaluation()) {
      throw new ForbiddenRequestException("Submission is not evaluated yet");
    }

    $stream = $this->fileServerProxy->getResultArchiveStream($submission->getResultsUrl());
    if ($stream === null) {
      throw new NotFoundException("Archive for submission '$id' not found on remote fileserver");
    }

    $this->sendResponse(new GuzzleResponse($stream, $id . '.zip'));
  }

}
