<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;
use App\Exception\ForbiddenRequestException;
use App\Exception\BadRequestException;
use App\Exception\SubmissionFailedException;

use App\Model\Entity\Submission;
use App\Model\Helpers\SubmissionHelper;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\Submissions;
use App\Model\Repository\UploadedFiles;

/**
 * @LoggedIn
 */
class ExerciseAssignmentsPresenter extends BasePresenter {

  /** @var ExerciseAssignments */
  private $assignments;

  /** @var Submissions */
  private $submissions;

  /** @var UploadedFiles */
  private $files;

  /** @var SubmissionHelper */
  private $submissionHelper;

  /**
   * @param Submissions $submissions  Submissions repository
   * @param ExerciseAssignments $assignments  Assignments repository
   * @param UploadedFiles $files  Uploaded files repository
   */
  public function __construct(
    Submissions $submissions,
    ExerciseAssignments $assignments,
    UploadedFiles $files,
    SubmissionHelper $submissionHelper
  ) {
    $this->submissions = $submissions;
    $this->assignments = $assignments;
    $this->files = $files;
    $this->submissionHelper = $submissionHelper;
  }

  protected function findAssignmentOrFail(string $id) {
    $assignment = $this->assignments->get($id);
    if (!$assignment) {
      throw new NotFoundException;
    }

    return $assignment;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $assignments = $this->assignments->findAll();
    $this->sendSuccessResponse($assignments);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $assignment = $this->findAssignmentOrFail($id);
    $this->sendSuccessResponse($assignment);
  }

  /**
   * @GET
   */
  public function actionSubmissions(string $id, string $userId) {
    $assignment = $this->findAssignmentOrFail($id);
    $submissions = $this->submissions->findSubmissions($assignment, $userId);
    $this->sendSuccessResponse($submissions);
  }

  /**
   * @POST
   * @Param(type="post", name="note")
   * @Param(type="post", name="files")
   */
  public function actionSubmit(string $id) {
    $assignment = $this->findAssignmentOrFail($id);
    $req = $this->getHttpRequest();

    $loggedInUser = $this->findUserOrThrow("me");
    $userId = $req->getPost("userId");
    if ($userId !== NULL) {
      $user = $this->findUserOrThrow($userId);
    } else {
      $user = $loggedInUser;
    }

    // create the submission record
    $hwGroup = "group1";
    $files = $this->files->findAllById($req->getPost("files"));
    $note = $req->getPost("note");
    $submission = Submission::createSubmission($note, $assignment, $user, $loggedInUser, $hwGroup, $files);

    // persist all the data in the database
    $this->submissions->persist($submission);
    foreach ($files as $file) {
      $this->files->persist($file);
    }

    $evaluationHasStarted = $this->submissionHelper->initiateEvaluation($submission);
    if($evaluationHasStarted === TRUE) {
      $this->submissions->persist($submission);
      $this->files->flush();
      $this->sendSuccessResponse([
        "submission" => $submission,
        "webSocketChannel" => [
          "id" => $submission->getId(),
          "expectedTasksCount" => $submission->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }
}
