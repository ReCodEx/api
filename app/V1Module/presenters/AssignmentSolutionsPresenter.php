<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerErrorException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\NotReadyException;
use App\Helpers\EvaluationLoadingHelper;
use App\Helpers\FileServerProxy;
use App\Helpers\Notifications\PointsChangedEmailsSender;
use App\Helpers\Validators;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Users;
use App\Model\View\AssignmentSolutionSubmissionViewFactory;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Exceptions\ForbiddenRequestException;
use App\Responses\GuzzleResponse;
use App\Responses\ZipFilesResponse;
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
   * @var AssignmentSolutionViewFactory
   * @inject
   */
  public $assignmentSolutionViewFactory;

  /**
   * @var AssignmentSolutionSubmissionViewFactory
   * @inject
   */
  public $assignmentSolutionSubmissionViewFactory;

  /**
   * @var PointsChangedEmailsSender
   * @inject
   */
  public $pointsChangedEmailsSender;


  public function checkSolution(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
      throw new ForbiddenRequestException("You cannot view details of this solution");
    }
  }

    /**
   * Get information about solutions.
   * @GET
   * @param string $id Identifier of the solution
   * @throws InternalServerErrorException
   */
  public function actionSolution(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);

    // if there is submission, try to evaluate it
    $submission = $solution->getLastSubmission();
    if ($submission) {
      $this->evaluationLoadingHelper->loadEvaluation($submission);
    }

    // fetch data
    $this->sendSuccessResponse(
      $this->assignmentSolutionViewFactory->getSolutionData($solution)
    );
  }

  public function checkDeleteSolution(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canDelete($solution)) {
      throw new ForbiddenRequestException("You cannot delete this assignment solution");
    }
  }

  /**
   * Delete assignment solution with given identification.
   * @DELETE
   * @param string $id identifier of assignment solution
   * @throws ForbiddenRequestException
   */
  public function actionDeleteSolution(string $id) {
    $this->assignmentSolutions->remove($this->assignmentSolutions->findOrThrow($id));
    $this->sendSuccessResponse("OK");
  }

  public function checkEvaluations(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
      throw new ForbiddenRequestException("You cannot access this solution evaluations");
    }
  }

  /**
   * Get information about the evaluations of a solution
   * @GET
   * @param string $id Identifier of the solution
   */
  public function actionEvaluations(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);

    $submissions = $this->assignmentSolutionAcl->canViewEvaluation($solution)
      ? $solution->getSubmissions()->getValues()
      : [];

    // display only data that the current user can view
    $submissions = array_map(function (AssignmentSolutionSubmission $submission) {
      // try to load evaluation if not present
      $this->evaluationLoadingHelper->loadEvaluation($submission);
      return $this->assignmentSolutionSubmissionViewFactory->getSubmissionData($submission);
    }, $submissions);

    $this->sendSuccessResponse($submissions);
  }

  public function checkEvaluation(string $evaluationId) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($evaluationId);
    $solution = $submission->getAssignmentSolution();
    if (!$this->assignmentSolutionAcl->canViewEvaluation($solution)) {
      throw new ForbiddenRequestException("You cannot access this evaluation");
    }
  }

  /**
   * Get information about the evaluation of a submission
   * @GET
   * @param string $evaluationId Identifier of the submission
   * @throws InternalServerErrorException
   */
  public function actionEvaluation(string $evaluationId) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($evaluationId);

    // try to load evaluation if not present
    $this->evaluationLoadingHelper->loadEvaluation($submission);

    $submissionData = $this->assignmentSolutionSubmissionViewFactory->getSubmissionData($submission);
    $this->sendSuccessResponse($submissionData);
  }

  public function checkDeleteEvaluation(string $evaluationId) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($evaluationId);
    $solution = $submission->getAssignmentSolution();
    if (!$this->assignmentSolutionAcl->canDeleteEvaluation($solution)) {
      throw new ForbiddenRequestException("You cannot delete this evaluation");
    }
    if ($solution->getSubmissions()->count() < 2) {
      throw new BadRequestException("You cannot delete last evaluation of a solution");
    }
  }

  /**
   * Remove evaluation (submission) permanently.
   * @DELETE
   * @param string $evaluationId Identifier of the submission
   */
  public function actionDeleteEvaluation(string $evaluationId) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($evaluationId);
    $this->assignmentSolutionSubmissions->remove($submission);
    $this->assignmentSolutionSubmissions->flush();
    $this->sendSuccessResponse("OK");
  }

  public function checkSetBonusPoints(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canSetBonusPoints($solution)) {
      throw new ForbiddenRequestException("You cannot change amount of bonus points for this submission");
    }
  }

  /**
   * Set new amount of bonus points for a solution
   * @POST
   * @Param(type="post", name="bonusPoints", validation="numericint", description="New amount of bonus points, can be negative number")
   * @Param(type="post", name="overriddenPoints", required=false, description="Overrides points assigned to solution by the system")
   * @param string $id Identifier of the submission
   * @throws NotFoundException
   * @throws InvalidArgumentException
   * @throws InvalidStateException
   */
  public function actionSetBonusPoints(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    $oldBonusPoints = $solution->getBonusPoints();
    $oldOverridenPoints = $solution->getOverriddenPoints();

    $newBonusPoints = $this->getRequest()->getPost("bonusPoints");
    $overriddenPoints = $this->getRequest()->getPost("overriddenPoints");

    $solution->setBonusPoints($newBonusPoints);

    // TODO: validations 'null|numericint' for overridenPoints cannot be used, because null is converted to empty string,
    // TODO: which immediately breaks stated validation... in the future, this behaviour has to change
    // TODO: lucky third TODO
    if (Validators::isNumericInt($overriddenPoints)) {
      $solution->setOverriddenPoints($overriddenPoints);
    } else if (empty($overriddenPoints)) {
      $solution->setOverriddenPoints(null);
    } else {
      throw new InvalidArgumentException("overridenPoints", "The value '$overriddenPoints' is not null|numericint");
    }

    if ($oldBonusPoints !== $newBonusPoints || $oldOverridenPoints !== $overriddenPoints) {
      $this->pointsChangedEmailsSender->solutionPointsUpdated($solution);
    }

    $this->assignmentSolutions->flush();
    $this->sendSuccessResponse("OK");
  }

  public function checkSetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canSetAccepted($solution)) {
      throw new ForbiddenRequestException("You cannot change accepted flag for this submission");
    }
  }

  /**
   * Set solution of student as accepted, this solution will be then presented as the best one.
   * @POST
   * @param string $id identifier of the submission
   * @throws \Nette\Application\AbortException
   */
  public function actionSetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);

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

  public function checkUnsetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canSetAccepted($solution)) {
      throw new ForbiddenRequestException("You cannot change accepted flag for this submission");
    }
  }

  /**
   * Set solution of student as unaccepted if it was.
   * @DELETE
   * @param string $id identifier of the submission
   * @throws \Nette\Application\AbortException
   */
  public function actionUnsetAcceptedSubmission(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);

    // set accepted flag as false even if it was false
    $solution->setAccepted(false);
    $this->assignmentSolutions->flush();

    // forward to student statistics of group
    $groupOfSubmission = $solution->getAssignment()->getGroup();
    $this->forward('Groups:studentsStats', $groupOfSubmission->getId(), $solution->getSolution()->getAuthor()->getId());
  }

  public function checkDownloadSolutionArchive(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
      throw new ForbiddenRequestException("You cannot access archive of solution files");
    }
  }

  /**
   * Download archive containing all solution files for particular solution.
   * @GET
   * @param string $id of assignment solution
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws \Nette\Application\BadRequestException
   * @throws \Nette\Application\AbortException
   */
  public function actionDownloadSolutionArchive(string $id) {
    $solution = $this->assignmentSolutions->findOrThrow($id);

    $files = [];
    foreach ($solution->getSolution()->getFiles() as $file) {
      $files[$file->getLocalFilePath()] = $file->getName();
    }
    $this->sendResponse(new ZipFilesResponse($files, "solution-{$id}.zip"));
  }

  public function checkDownloadResultArchive(string $evaluationId) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($evaluationId);
    if (!$this->assignmentSolutionAcl->canDownloadResultArchive($submission->getAssignmentSolution())) {
      throw new ForbiddenRequestException("You cannot access the result archive for this submission");
    }
  }

  /**
   * Download result archive from backend for particular submission.
   * @GET
   * @param string $evaluationId
   * @throws NotFoundException
   * @throws InternalServerErrorException
   * @throws \Nette\Application\AbortException
   */
  public function actionDownloadResultArchive(string $evaluationId) {
    $submission = $this->assignmentSolutionSubmissions->findOrThrow($evaluationId);
    $this->evaluationLoadingHelper->loadEvaluation($submission);

    if (!$submission->hasEvaluation()) {
      throw new NotReadyException("Submission is not evaluated yet");
    }

    $stream = $this->fileServerProxy->getFileserverFileStream($submission->getResultsUrl());
    if ($stream === null) {
      throw new NotFoundException("Archive for submission '$evaluationId' not found on remote fileserver");
    }

    $this->sendResponse(new GuzzleResponse($stream, "results-{$evaluationId}.zip", "application/zip"));
  }

}
