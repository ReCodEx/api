<?php

namespace App\V1Module\Presenters;

use App\Exceptions\InternalServerErrorException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\BrokerConfig;
use App\Helpers\FailureHelper;
use App\Helpers\EmailHelper;
use App\Helpers\EvaluationLoader;
use App\Helpers\BasicAuthHelper;
use App\Helpers\JobConfig\JobId;
use App\Model\Entity\Submission;
use App\Model\Entity\ReferenceSolutionEvaluation;
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
   * @var EmailHelper
   * @inject
   */
  public $emailHelper;

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
   * @var SolutionEvaluations
   * @inject
   */
  public $evaluations;

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
   */
  public function actionJobStatus($jobId) {
    $status = $this->getHttpRequest()->getPost("status");
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
              $this->loadEvaluation($submission);
            }
            break;
        }
        break;
      case self::STATUS_FAILED:
        $message = $this->getHttpRequest()->getPost("message", "");
        $this->failureHelper->report(
          FailureHelper::TYPE_BACKEND_ERROR,
          "Broker reports job '$jobId' (type: '{$job->getType()}', id: '{$job->getId()}') processing failure: $message"
        );
        break;
    }

    $this->sendSuccessResponse($submission);
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
    }

    $submission->setEvaluation($evaluation);
    $this->evaluations->persist($evaluation);
    $this->submissions->persist($submission);

    // the solution is allways asynchronous here, so send notification to the user
    $this->notifyEvaluationFinished($submission);
  }

  private function loadReferenceEvaluation(ReferenceSolutionEvaluation $referenceSolutionEvaluation) {
    try {
      $solutionEvaluation = $this->evaluationLoader->loadReference($referenceSolutionEvaluation);
    } catch (SubmissionEvaluationFailedException $e) {
      // the result cannot be loaded even though the result MUST be ready at this point
      $this->failureHelper->report(
        FailureHelper::TYPE_API_ERROR,
        "Evaluation results of the job with ID '{$submission->getId()}' could not be processed. {$e->getMessage()}"
      );
    }

    $referenceSolutionEvaluation->setEvaluation($solutionEvaluation);
    $this->evaluations->persist($solutionEvaluation);
    $this->referenceSolutionEvaluations->persist($referenceSolutionEvaluation);
  }

  /**
   * @todo Rewrite this method completely!!
   * @param Submission $submission  Evaluated submission
   * @return void
   */
  private function notifyEvaluationFinished(Submission $submission) {
    $exerciseName = $submission->getAssignment()->getName();
    $date = $submission->getEvaluation()->getEvaluatedAt()->format('j. n. H:i'); // @todo: Localizable
    $status = $successful === TRUE ? "Řešení je v pořádku" : "Řešení je chybné";  // @todo: Translatable
    $subject = "$exerciseName - $status [$date]";
    $text = "Vaše řešení úlohy '$exerciseName' bylo vyhodnoceno. $status. Podrobnosti naleznete na webu <a href='http://recodex.projekty.ms.mff.cuni.cz'>ReCodEx</a>."; // @todo: Translatable
    $email = $submission->getUser()->getEmail();
    $this->emailHelper->send("noreply@recodex.org", [ $email ], $subject, $text); // @todo: Load the sender from config
  }

  /**
   * Announce a backend error that is not related to any job (meant to be called by the backend)
   * @POST
   * @Param(name="message", type="post", description="A textual description of the error")
   */
  public function actionError() {
    $req = $this->getHttpRequest();
    $message = $req->getPost("message");
    if (!$this->failureHelper->report(FailureHelper::TYPE_BACKEND_ERROR, $message)) {
      throw new InternalServerErrorException("Error could not have been reported to the admin because of an internal server error.");
    }

    $this->sendSuccessResponse("Error was reported.");
  }

}
