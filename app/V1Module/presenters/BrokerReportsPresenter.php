<?php

namespace App\V1Module\Presenters;

use App\Exceptions\InternalServerErrorException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\BrokerConfig;
use App\Helpers\EmailsConfig;
use App\Helpers\FailureHelper;
use App\Helpers\EvaluationLoader;
use App\Helpers\BasicAuthHelper;
use App\Helpers\JobConfig\JobId;
use App\Helpers\Notifications\SubmissionEmailsSender;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\Submission;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Model\Entity\SubmissionFailure;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Submissions;
use App\Model\Repository\SolutionEvaluations;
use App\Model\Repository\ReferenceSolutionEvaluations;

/**
 * Endpoints used by the backend to notify the frontend of errors and changes in job status
 */
class BrokerReportsPresenter extends BasePresenter {

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";

  /**
   * @var FailureHelper
   * @inject
   */
  public $failureHelper;

  /**
   * @var SubmissionEmailsSender
   * @inject
   */
  public $submissionEmailsSender;

  /**
   * @var EvaluationLoader
   * @inject
   */
  public $evaluationLoader;

  /**
   * @var Submissions
   * @inject
   */
  public $submissions;

  /**
   * @var SubmissionFailures
   * @inject
   */
  public $submissionFailures;

  /**
   * @var SolutionEvaluations
   * @inject
   */
  public $evaluations;

  /**
   * @var ReferenceExerciseSolutions
   * @inject
   */
  public $referenceSolutions;

  /**
   * @var ReferenceSolutionEvaluations
   * @inject
   */
  public $referenceSolutionEvaluations;

  /**
   * @var BrokerConfig
   * @inject
   */
  public $brokerConfig;

  /**
   * @var EmailsConfig
   * @inject
   */
  public $emailsConfig;

  /**
   * The actions of this presenter have specific
   */
  public function startup() {
    $req = $this->getHttpRequest();
    list($username, $password) = BasicAuthHelper::getCredentials($req);

    $isAuthCorrect = $username === $this->brokerConfig->getAuthUsername()
      && $password === $this->brokerConfig->getAuthPassword();

    if (!$isAuthCorrect) {
      throw new WrongCredentialsException;
    }

    parent::startup();
  }

  /**
   * Update the status of a job (meant to be called by the backend)
   * @POST
   * @Param(name="status", type="post", description="The new status of the job")
   * @Param(name="message", type="post", required=false, description="A textual explanation of the status change")
   * @param string $jobId Identifier of the job whose status is being reported
   */
  public function actionJobStatus($jobId) {
    $status = $this->getRequest()->getPost("status");
    $job = new JobId($jobId);

    switch ($status) {
      case self::STATUS_OK:
        switch ($job->getType()) {
          case ReferenceSolutionEvaluation::JOB_TYPE:
            // load the evaluation of the reference solution now
            $referenceSolutionEvaluation = $this->referenceSolutionEvaluations->findOrThrow($job->getId());
            $this->loadReferenceEvaluation($referenceSolutionEvaluation);
            break;
          case Submission::JOB_TYPE:
            $submission = $this->submissions->findOrThrow($job->getId());
            // load the evaluation only if the submission is "async"
            // (submitted by other person than the student/author or automatically)
            if ($submission->isAsynchronous()) {
              $result = $this->loadEvaluation($submission);

              if ($result === TRUE) {
                // the solution is always asynchronous here, so send notification to the user
                $this->submissionEmailsSender->submissionEvaluated($submission);
              }
            }
            break;
        }
        break;
      case self::STATUS_FAILED:
        $message = $this->getRequest()->getPost("message") ?: "";
        $this->failureHelper->report(
          FailureHelper::TYPE_BACKEND_ERROR,
          "Broker reports job '$jobId' (type: '{$job->getType()}', id: '{$job->getId()}') processing failure: $message"
        );

        switch ($job->getType()) {
          case Submission::JOB_TYPE:
            $submission = $this->submissions->findOrThrow($job->getId());
            $failureReport = SubmissionFailure::forSubmission(SubmissionFailure::TYPE_EVALUATION_FAILURE, $message, $submission);
            $this->submissionFailures->persist($failureReport);
            break;
          case ReferenceSolutionEvaluation::JOB_TYPE:
            $referenceSolutionEvaluation = $this->referenceSolutionEvaluations->findOrThrow($job->getId());
            $failureReport = SubmissionFailure::forReferenceSolution(SubmissionFailure::TYPE_EVALUATION_FAILURE, $message, $referenceSolutionEvaluation);
            $this->submissionFailures->persist($failureReport);
            break;
        }

        break;
    }

    $this->sendSuccessResponse("OK");
  }

  private function loadEvaluation(Submission $submission) {
    try {
      $evaluation = $this->evaluationLoader->load($submission);
    } catch (SubmissionEvaluationFailedException $e) {
      // the result cannot be loaded even though the result MUST be ready at this point
      $this->failureHelper->report(
        FailureHelper::TYPE_API_ERROR,
        "Evaluation results of the job with ID '{$submission->getId()}' could not be processed. {$e->getMessage()}"
      );

      return FALSE;
    }

    $submission->setEvaluation($evaluation);
    $this->evaluations->persist($evaluation);
    $this->submissions->persist($submission);

    return TRUE;
  }

  private function loadReferenceEvaluation(ReferenceSolutionEvaluation $referenceSolutionEvaluation) {
    $referenceSolution = $referenceSolutionEvaluation->getReferenceSolution();
    try {
      $solutionEvaluation = $this->evaluationLoader->loadReference($referenceSolutionEvaluation);
    } catch (SubmissionEvaluationFailedException $e) {
      // the result cannot be loaded even though the result MUST be ready at this point
      $this->failureHelper->report(
        FailureHelper::TYPE_API_ERROR,
        "Evaluation results of the job with ID '{$referenceSolution->getId()}' could not be processed. {$e->getMessage()}"
      );
      return;
    }

    $referenceSolutionEvaluation->setEvaluation($solutionEvaluation);
    $this->evaluations->persist($solutionEvaluation);
    $this->referenceSolutionEvaluations->persist($referenceSolutionEvaluation);
  }

  /**
   * Announce a backend error that is not related to any job (meant to be called by the backend)
   * @POST
   * @Param(name="message", type="post", description="A textual description of the error")
   */
  public function actionError() {
    $req = $this->getRequest();
    $message = $req->getPost("message");
    if (!$this->failureHelper->report(FailureHelper::TYPE_BACKEND_ERROR, $message)) {
      throw new InternalServerErrorException("Error could not have been reported to the admin because of an internal server error.");
    }

    $this->sendSuccessResponse("Error was reported.");
  }

}
