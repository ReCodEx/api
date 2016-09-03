<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;
use App\Exception\ForbiddenRequestException;
use App\Exception\BadRequestException;
use App\Exception\SubmissionFailedException;

use App\Model\Entity\Submission;
use App\Model\Helpers\SubmissionHelper;
use App\Model\Helpers\JobConfig;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\Submissions;
use App\Model\Repository\UploadedFiles;

/**
 * @LoggedIn
 */
class ExerciseAssignmentsPresenter extends BasePresenter {

  /** @inject @var ExerciseAssignments */
  public $assignments;

  /** @inject @var Submissions */
  public $submissions;

  /** @inject @var UploadedFiles */
  public $files;

  /** @inject @var SubmissionHelper */
  public $submissionHelper;

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

    // persist all the data in the database - this will also assign the UUID to the submission
    $this->submissions->persist($submission);
    $jobConfig = JobConfig\Loader::getJobConfig($submission);

    $resultsUrl = $this->submissionHelper->initiateEvaluation(
      $jobConfig,
      $submission->getFiles()->toArray(),
      $hwGroup
    );

    if($resultsUrl !== NULL) {
      $submission->setResultsUrl($resultsUrl);
      $this->submissions->persist($submission);
      foreach ($files as $file) { $this->files->persist($file); }
      $this->files->flush();
      $this->sendSuccessResponse([
        "submission" => $submission,
        "webSocketChannel" => [
          "id" => $jobConfig->getJobId(),
          "monitorUrl" => $this->getContext()->parameters['monitor']['address'],
          "expectedTasksCount" => $jobConfig->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }
}
