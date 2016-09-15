<?php

namespace App\V1Module\Presenters;

use App\Exceptions\InternalServerErrorException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\FailureHelper;
use App\Helpers\EmailHelper;
use App\Helpers\EvaluationLoader;
use App\Helpers\BasicAuthHelper;
use App\Model\Entity\Submission;
use App\Model\Repository\Submissions;
use Nette\Utils\Strings;
use Nette\Utils\Arrays;

class BrokerReportsPresenter extends BasePresenter {

  const STATUS_OK = "OK";
  const STATUS_FAILED = "FAILED";

  /** @inject @var FailureHelper */
  public $failureHelper;

  /** @inject @var FailureHelper */
  public $emailHelper;

  /** @inject @var EvaluationLoader */
  public $evaluationLoader;

  /** @inject @var Submissions */
  public $submissions;

  /**
   * @param string $id
   * @return mixed
   * @throws NotFoundException
   */
  protected function findSubmissionOrThrow(string $id) {
    $submission = $this->submissions->get($id);
    if (!$submission) {
      throw new NotFoundException("Submission $id");
    }

    return $submission;
  }

  /**
   * The actions of this presenter have specific 
   */
  public function startup() {
    $req = $this->getHttpRequest();
    list($username, $password) = BasicAuthHelper::getCredentials($req);

    $params = $this->getContext()->getParameters();
    $expectedUsername = Arrays::get($params, ["broker", "auth", "username"], NULL);
    $expectedPassword = Arrays::get($params, ["broker", "auth", "password"], NULL);

    if ($username !== $expectedUsername || $password !== $expectedPassword) {
      throw new WrongCredentialsException;
    }

    parent::startup();
  }

  /**
   * @POST
   * @Param(name="status", type="post")
   * @Param(name="message", type="post", required=false)
   */
  public function actionJobStatus($submissionId) {
    $status = $this->getHttpRequest()->getPost("status");

    switch ($status) {
      case self::STATUS_OK:
        $submission = $this->findSubmissionOrThrow($submissionId);
        $this->loadEvaluation($submission);
        break;
      case self::STATUS_FAILED:
        $message = $this->getHttpRequest()->getPost("message", "");
        $this->failureHelper->report(
          FailureHelper::TYPE_BACKEND_ERROR,
          "Broker reports job $submissionId processing failure: $message"
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

    $this->evaluations->persist($evaluation);
    $this->submissions->persist($submission);

    if ($submission->shouldNotifyAfterEvaluationIsLoader()) {
      $this->notifyEvaluationFinished($submission);
    }
  }

  /**
   * @todo Rewrite this method completely!!
   * @param Submission $submission  Evaluated submission
   * @return void
   */
  private function notifyEvaluationFinished(Submission $submission) {
    $exerciseName = $submission->getExerciseAssignment()->getName();
    $date = $submission->getEvaluation()->getEvaluatedAt()->format('j. n. H:i'); // @todo: Localizable
    $status = $successful === TRUE ? "Řešení je v pořádku" : "Řešení je chybné";  // @todo: Translatable
    $subject = "$exerciseName - $status [$date]";
    $text = "Vaše řešení úlohy '$exerciseName' bylo vyhodnoceno. $status. Podrobnosti naleznete na webu <a href='http://recodex.projekty.ms.mff.cuni.cz'>ReCodEx</a>."; // @todo: Translatable
    $email = $submission->getUser()->getEmail();
    $this->emailHelper->send("noreply@recodex.org", [ $email ], $subject, $text); // @todo: Load the sender from config
  }

  /**
   * @POST
   * @Param(name="message", type="post")
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
