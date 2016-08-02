<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;
use App\Exception\ForbiddenRequestException;
use App\Exception\BadRequestException;
use App\Exception\SubmissionFailedException;

use App\Model\Entity\Submission;
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

  /**
   * @param Submissions $submissions  Submissions repository
   * @param ExerciseAssignments $assignments  Assignments repository
   * @param UploadedFiles $files  Uploaded files repository
   */
  public function __construct(Submissions $submissions, ExerciseAssignments $assignments, UploadedFiles $files) {
    $this->submissions = $submissions;
    $this->assignments = $assignments;
    $this->files = $files;
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
   * @RequiredField(type="post", name="note")
   * @RequiredField(type="post", name="files")
   */
  public function actionSubmit(string $id) {
    $assignment = $this->findAssignmentOrFail($id);
    $req = $this->getHttpRequest();

    $loggedInUser = $this->findUserOrThrow('me');
    $userId = $req->getPost('userId');
    if ($userId !== NULL) {
      $user = $this->findUserOrThrow($userId);
    } else {
      $user = $loggedInUser;
    }

    // collect the array of already uploaded files
    $files = $this->files->findAllById($req->getPost('files'));

    // prepare a record in the database
    $submission = Submission::createSubmission($req->getPost('note'), $assignment, $user, $loggedInUser, $files);

    // persist all the data in the database
    $this->submissions->persist($submission);
    foreach ($files as $file) {
      $this->files->persist($file);
    }

    // send the data to the evaluation server
    if($submission->submit() === TRUE) { // true does not mean the solution is OK, just that the evaluation server accepted it and started evaluating it
      $this->submissions->persist($submission);
      $this->files->flush();
      $this->sendSuccessResponse([
        'submission' => $submission,
        'webSocketChannel' => [
          'id' => $submission->getId(),
          'expectedTasksCount' => $submission->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }

}
