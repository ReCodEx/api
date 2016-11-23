<?php

namespace App\V1Module\Presenters;

use App\Helpers\EvaluationLoader;
use App\Model\Repository\Submissions;
use App\Model\Repository\SolutionEvaluations;
use App\Model\Repository\Users;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;

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

    $this->sendSuccessResponse($submission);
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

}
