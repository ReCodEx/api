<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotFoundException;
use App\Helpers\EvaluationLoader;
use App\Helpers\FileServerProxy;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\Group;
use App\Model\Entity\AssignmentSolution;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolutionSubmissions;
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
   * Get information about the evaluations of a solution
   * @GET
   * @param string $id Identifier of the solution
   * @throws ForbiddenRequestException
   */
  public function actionEvaluations(string $id) { // TODO: test
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->submissionAcl->canViewDetail($solution)) { // TODO
      throw new ForbiddenRequestException("You cannot access this solution evaluations");
    }

    $submissions = array_filter($solution->getSubmissions()->getValues(),
      function (AssignmentSolutionSubmission $submission) use ($solution) {
        return $this->submissionAcl->canViewEvaluation($solution, $submission);
    });

    // display only proper data for logged user
    $submissions = array_map(function (AssignmentSolutionSubmission $submission) use ($solution) {
      $canViewDetails = $this->submissionAcl->canViewEvaluationDetails($solution, $submission);
      $canViewValues = $this->submissionAcl->canViewEvaluationValues($solution, $submission);
      return $submission->getData($canViewDetails, $canViewValues);
    }, $submissions);

    $this->sendSuccessResponse($submissions);
  }

  /**
   * Get information about the evaluation of a submission
   * @GET
   * @param string $id Identifier of the submission
   * @throws ForbiddenRequestException
   */
  public function actionEvaluation(string $id) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($id);
    if (!$this->submissionAcl->canViewEvaluation($submission->getAssignmentSolution(), $submission)) {
      throw new ForbiddenRequestException("You cannot access this evaluation");
    }

    if (!$submission->hasEvaluation()) { // the evaluation must be loaded first
      $evaluation = $this->evaluationLoader->load($submission);
      if ($evaluation !== NULL) {
        $this->evaluations->persist($evaluation);
        $this->assignmentSolutions->persist($submission);
      } else {
        // the evaluation is probably not ready yet
        // - display partial information about the submission, do not throw an error
      }
    }

    $solution = $submission->getAssignmentSolution();
    $canViewDetails = $this->submissionAcl->canViewEvaluationDetails($solution, $submission);
    $canViewValues = $this->submissionAcl->canViewEvaluationValues($solution, $submission);
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

    if (!$this->submissionAcl->canSetBonusPoints($solution)) {
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
   */
  public function actionSetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->submissionAcl->canSetAccepted($solution)) {
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
   */
  public function actionUnsetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->submissionAcl->canSetAccepted($solution)) {
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
   */
  public function actionDownloadResultArchive(string $id) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($id);
    if (!$this->submissionAcl->canDownloadResultArchive($submission->getAssignmentSolution(), $submission)) {
      throw new ForbiddenRequestException("You cannot access result archive for this submission");
    }

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
