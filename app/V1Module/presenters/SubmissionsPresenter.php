<?php

namespace App\V1Module\Presenters;

use App\Helpers\EvaluationLoader;
use App\Helpers\FileServerProxy;
use App\Model\Repository\Submissions;
use App\Model\Repository\SolutionEvaluations;
use App\Model\Repository\Users;
use App\Exceptions\ForbiddenRequestException;
use App\Responses\GuzzleResponse;

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
   * Get a list of all submissions, ever
   * @GET
   * @UserIsAllowed(submissions="view-all")
   */
  public function actionDefault() {
    $submissions = $this->submissions->findAll();
    $this->sendSuccessResponse($submissions);
  }

  /**
   * Get information about the evaluation of a submission
   * @GET
   * @UserIsAllowed(submissions="view-evaluation")
   * @param string $id Identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionEvaluation(string $id) {
    $submission = $this->submissions->findOrThrow($id);
    $currentUser = $this->getCurrentUser();
    $groupOfSubmission = $submission->getAssignment()->getGroup();

    $isFileOwner = $submission->getUser()->getId() === $currentUser->getId();
    $isSupervisor = $groupOfSubmission->isSupervisorOf($currentUser);
    $isAdmin = $groupOfSubmission->isAdminOf($currentUser) || !$currentUser->getRole()->hasLimitedRights();

    if (!$isFileOwner && !$isSupervisor && !$isAdmin) {
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

    $canViewDetails = $submission->getAssignment()->getCanViewLimitRatios() || $isAdmin || $isSupervisor;
    $this->sendSuccessResponse($submission->getData($canViewDetails));
  }

  /**
   * Set new amount of bonus points for a submission
   * @POST
   * @Param(type="post", name="bonusPoints", validation="numericint", description="New amount of bonus points, can be negative number")
   * @UserIsAllowed(submissions="set-bonus-points")
   * @param string $id Identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionSetBonusPoints(string $id) {
    $newBonusPoints = $this->getRequest()->getPost("bonusPoints");
    $submission = $this->submissions->findOrThrow($id);
    $evaluation = $submission->getEvaluation();

    $currentUser = $this->getCurrentUser();
    $groupOfSubmission = $submission->getAssignment()->getGroup();
    $isSupervisor = $groupOfSubmission->isSupervisorOf($currentUser);
    $isAdmin = $groupOfSubmission->isAdminOf($currentUser) || !$currentUser->getRole()->hasLimitedRights();
    if (!$isSupervisor && !$isAdmin) {
      throw new ForbiddenRequestException("You cannot change amount of bonus points for this submission");
    }

    $evaluation->setBonusPoints($newBonusPoints);
    $this->evaluations->persist($evaluation);

    $this->sendSuccessResponse("OK");
  }

  /**
   * Download result archive from backend for particular submission.
   * @GET
   * @UserIsAllowed(submissions="download-result-archive")
   * @param string $id
   * @throws ForbiddenRequestException
   */
  public function actionDownloadResultArchive(string $id) {
    $submission = $this->submissions->findOrThrow($id);

    $currentUser = $this->getCurrentUser();
    $groupOfSubmission = $submission->getAssignment()->getGroup();
    $isSupervisor = $groupOfSubmission->isSupervisorOf($currentUser);
    $isAdmin = $groupOfSubmission->isAdminOf($currentUser) || !$currentUser->getRole()->hasLimitedRights();
    if (!$isSupervisor && !$isAdmin) {
      throw new ForbiddenRequestException("You cannot access result archive for this submission");
    }

    if (!$submission->hasEvaluation()) {
      throw new ForbiddenRequestException("Submission is not evaluated yet");
    }

    $stream = $this->fileServerProxy->getResultArchiveStream($submission->getResultsUrl());
    $this->sendResponse(new GuzzleResponse($stream, $id . '.zip'));
  }

}
