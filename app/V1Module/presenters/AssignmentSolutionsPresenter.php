<?php

namespace App\V1Module\Presenters;

use App\Exceptions\InternalServerErrorException;
use App\Exceptions\NotFoundException;
use App\Helpers\EvaluationLoadingHelper;
use App\Helpers\FileServerProxy;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Users;
use App\Exceptions\ForbiddenRequestException;
use App\Responses\GuzzleResponse;
use App\Security\ACL\IAssignmentSolutionPermissions;

/**
 * Endpoints for manipulation of solution submissions
 * @LoggedIn
 */
class AssignmentSolutionsPresenter extends BasePresenter {

  /**
   * @var AssignmentSolutions
   * @inject
   */
  public $assignmentSolutions;

  /**
   * @var AssignmentSolutionSubmissions
   * @inject
   */
  public $assignmentSolutionSubmissions;

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
   * @var IAssignmentSolutionPermissions
   * @inject
   */
  public $assignmentSolutionAcl;

  /**
   * @var SubmissionFailures
   * @inject
   */
  public $submissionFailures;

  /**
   * @var EvaluationLoadingHelper
   * @inject
   */
  public $evaluationLoadingHelper;

  /**
   * Get information about solutions.
   * @GET
   * @param string $id Identifier of the solution
   * @throws ForbiddenRequestException
   * @throws InternalServerErrorException
   */
  public function actionSolution(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
      throw new ForbiddenRequestException("You cannot change amount of bonus points for this submission");
    }

    // if there is submission, try to evaluate it
    $submission = $solution->getLastSubmission();
    if ($submission) {
      $this->evaluationLoadingHelper->loadEvaluation($submission);
    }

    // fetch data
    $canViewDetails = $this->assignmentSolutionAcl->canViewEvaluationDetails($solution, $submission);
    $canViewValues = $this->assignmentSolutionAcl->canViewEvaluationValues($solution, $submission);
    $canViewResubmissions = $this->assignmentSolutionAcl->canViewResubmissions($solution);
    $this->sendSuccessResponse($solution->getData($canViewDetails, $canViewValues, $canViewResubmissions));
  }

  /**
   * Get information about the evaluations of a solution
   * @GET
   * @param string $id Identifier of the solution
   * @throws ForbiddenRequestException
   */
  public function actionEvaluations(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
      throw new ForbiddenRequestException("You cannot access this solution evaluations");
    }

    $submissions = array_filter($solution->getSubmissions()->getValues(),
      function (AssignmentSolutionSubmission $submission) use ($solution) {
        return $this->assignmentSolutionAcl->canViewEvaluation($solution, $submission);
    });

    // display only data that the current user can view
    $submissions = array_map(function (AssignmentSolutionSubmission $submission) use ($solution) {
      // try to load evaluation if not present
      $this->evaluationLoadingHelper->loadEvaluation($submission);

      $canViewDetails = $this->assignmentSolutionAcl->canViewEvaluationDetails($solution, $submission);
      $canViewValues = $this->assignmentSolutionAcl->canViewEvaluationValues($solution, $submission);
      return $submission->getData($canViewDetails, $canViewValues);
    }, $submissions);

    $this->sendSuccessResponse($submissions);
  }

  /**
   * Get information about the evaluation of a submission
   * @GET
   * @param string $id Identifier of the submission
   * @throws ForbiddenRequestException
   * @throws InternalServerErrorException
   */
  public function actionEvaluation(string $id) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($id);
    $solution = $submission->getAssignmentSolution();
    if (!$this->assignmentSolutionAcl->canViewEvaluation($submission->getAssignmentSolution(), $submission)) {
      throw new ForbiddenRequestException("You cannot access this evaluation");
    }

    // try to load evaluation if not present
    $this->evaluationLoadingHelper->loadEvaluation($submission);

    $canViewDetails = $this->assignmentSolutionAcl->canViewEvaluationDetails($solution, $submission);
    $canViewValues = $this->assignmentSolutionAcl->canViewEvaluationValues($solution, $submission);
    $this->sendSuccessResponse($submission->getData($canViewDetails, $canViewValues));
  }

  /**
   * Set new amount of bonus points for a solution
   * @POST
   * @Param(type="post", name="bonusPoints", validation="numericint", description="New amount of bonus points, can be negative number")
   * @param string $id Identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionSetBonusPoints(string $id) {
    $newBonusPoints = $this->getRequest()->getPost("bonusPoints");
    $solution = $this->assignmentSolutions->findOrThrow($id);

    if (!$this->assignmentSolutionAcl->canSetBonusPoints($solution)) {
      throw new ForbiddenRequestException("You cannot change amount of bonus points for this submission");
    }

    $solution->setBonusPoints($newBonusPoints);
    $this->assignmentSolutions->flush();

    $this->sendSuccessResponse("OK");
  }

  /**
   * Set solution of student as accepted, this solution will be then presented as the best one.
   * @POST
   * @param string $id identifier of the submission
   * @throws ForbiddenRequestException
   * @throws \Nette\Application\AbortException
   */
  public function actionSetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canSetAccepted($solution)) {
      throw new ForbiddenRequestException("You cannot change accepted flag for this submission");
    }

    // accepted flag has to be set to false for all other submissions
    $assignmentSubmissions = $this->assignmentSolutions->findSolutions($solution->getAssignment(), $solution->getSolution()->getAuthor());
    foreach ($assignmentSubmissions as $assignmentSubmission) {
      $assignmentSubmission->setAccepted(false);
    }

    // finally set the right submission as accepted
    $solution->setAccepted(true);
    $this->assignmentSolutions->flush();

    // forward to student statistics of group
    $groupOfSubmission = $solution->getAssignment()->getGroup();
    $this->forward('Groups:studentsStats', $groupOfSubmission->getId(), $solution->getSolution()->getAuthor()->getId());
  }

  /**
   * Set solution of student as unaccepted if it was.
   * @DELETE
   * @param string $id identifier of the submission
   * @throws ForbiddenRequestException
   * @throws \Nette\Application\AbortException
   */
  public function actionUnsetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canSetAccepted($solution)) {
      throw new ForbiddenRequestException("You cannot change accepted flag for this submission");
    }

    // set accepted flag as false even if it was false
    $solution->setAccepted(false);
    $this->assignmentSolutions->flush();

    // forward to student statistics of group
    $groupOfSubmission = $solution->getAssignment()->getGroup();
    $this->forward('Groups:studentsStats', $groupOfSubmission->getId(), $solution->getSolution()->getAuthor()->getId());
  }

  /**
   * Download result archive from backend for particular submission.
   * @GET
   * @param string $id
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws InternalServerErrorException
   * @throws \Nette\Application\AbortException
   */
  public function actionDownloadResultArchive(string $id) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canDownloadResultArchive($submission->getAssignmentSolution(), $submission)) {
      throw new ForbiddenRequestException("You cannot access result archive for this submission");
    }

    $this->evaluationLoadingHelper->loadEvaluation($submission);

    if (!$submission->hasEvaluation()) {
      throw new ForbiddenRequestException("Submission is not evaluated yet");
    }

    $stream = $this->fileServerProxy->getResultArchiveStream($submission->getResultsUrl());
    if ($stream === null) {
      throw new NotFoundException("Archive for submission '$id' not found on remote fileserver");
    }

    $this->sendResponse(new GuzzleResponse($stream, $id . '.zip', "application/zip"));
  }

}
