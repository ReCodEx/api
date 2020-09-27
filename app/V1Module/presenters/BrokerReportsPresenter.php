<?php

namespace App\V1Module\Presenters;

use App\Exceptions\HttpBasicAuthException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\NotImplementedException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\BrokerConfig;
use App\Helpers\EvaluationLoadingHelper;
use App\Helpers\FailureHelper;
use App\Helpers\BasicAuthHelper;
use App\Helpers\JobConfig\JobId;
use App\Helpers\Notifications\SubmissionEmailsSender;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\SubmissionFailure;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\ReferenceSolutionSubmissions;

/**
 * Endpoints used by the backend to notify the frontend of errors and changes in job status
 */
class BrokerReportsPresenter extends BasePresenter
{
    public const STATUS_OK = "OK";
    public const STATUS_FAILED = "FAILED";

    /**
     * @var FailureHelper
     * @inject
     */
    public $failureHelper;

    /**
     * @var AssignmentSolutionSubmissions
     * @inject
     */
    public $submissions;

    /**
     * @var SubmissionFailures
     * @inject
     */
    public $submissionFailures;

    /**
     * @var ReferenceSolutionSubmissions
     * @inject
     */
    public $referenceSolutionSubmissions;

    /**
     * @var BrokerConfig
     * @inject
     */
    public $brokerConfig;

    /**
     * @var EvaluationLoadingHelper
     * @inject
     */
    public $evaluationLoadingHelper;

    /**
     * @var SubmissionEmailsSender
     * @inject
     */
    public $submissionEmailsSender;


    /**
     * The actions of this presenter have specific
     * @throws WrongCredentialsException
     * @throws HttpBasicAuthException
     * @throws NotImplementedException
     */
    public function startup()
    {
        $req = $this->getHttpRequest();
        list($username, $password) = BasicAuthHelper::getCredentials($req);

        $isAuthCorrect = $username === $this->brokerConfig->getAuthUsername()
            && $password === $this->brokerConfig->getAuthPassword();

        if (!$isAuthCorrect) {
            throw new WrongCredentialsException();
        }

        parent::startup();
    }

    /**
     * Update the status of a job (meant to be called by the backend)
     * @POST
     * @Param(name="status", type="post", description="The new status of the job")
     * @Param(name="message", type="post", required=false, description="A textual explanation of the status change")
     * @param string $jobId Identifier of the job whose status is being reported
     * @throws InternalServerException
     * @throws NotFoundException
     * @throws InvalidStateException
     */
    public function actionJobStatus($jobId)
    {
        $status = $this->getRequest()->getPost("status");
        $job = new JobId($jobId);

        switch ($status) {
            case self::STATUS_OK:
                switch ($job->getType()) {
                    case ReferenceSolutionSubmission::JOB_TYPE:
                        // load the evaluation of the reference solution now
                        $referenceSolutionEvaluation = $this->referenceSolutionSubmissions->findOrThrow($job->getId());
                        $this->evaluationLoadingHelper->loadEvaluation($referenceSolutionEvaluation);
                        break;
                    case AssignmentSolution::JOB_TYPE:
                        $submission = $this->submissions->findOrThrow($job->getId());
                        // load the evaluation of the student submission (or a resubmission of a student submission)
                        $result = $this->evaluationLoadingHelper->loadEvaluation($submission);

                        if ($result) {
                            // optionally send notification to the user about evaluation of the solution
                            $this->submissionEmailsSender->submissionEvaluated($submission);
                        }
                        break;
                }
                break;
            case self::STATUS_FAILED:
                $message = $this->getRequest()->getPost("message") ?: "";
                $reportMessage = "Broker reports job '$jobId' (type: '{$job->getType()}', id: '{$job->getId()}') processing failure: $message";
                $failureReport = SubmissionFailure::create(SubmissionFailure::TYPE_EVALUATION_FAILURE, $reportMessage);

                switch ($job->getType()) {
                    case AssignmentSolution::JOB_TYPE:
                        $submission = $this->submissions->findOrThrow($job->getId());
                        $submission->setFailure($failureReport);
                        $this->submissionFailures->persist($failureReport);
                        $this->submissions->persist($submission);
                        $this->failureHelper->reportSubmissionFailure($submission, FailureHelper::TYPE_BACKEND_ERROR);
                        break;
                    case ReferenceSolutionSubmission::JOB_TYPE:
                        $submission = $this->referenceSolutionSubmissions->findOrThrow($job->getId());
                        $submission->setFailure($failureReport);
                        $this->submissionFailures->persist($failureReport);
                        $this->referenceSolutionSubmissions->persist($submission);
                        $this->failureHelper->reportSubmissionFailure($submission, FailureHelper::TYPE_BACKEND_ERROR);
                        break;
                }

                break;
        }

        $this->sendSuccessResponse("OK");
    }

    /**
     * Announce a backend error that is not related to any job (meant to be called by the backend)
     * @POST
     * @Param(name="message", type="post", description="A textual description of the error")
     * @throws InternalServerException
     */
    public function actionError()
    {
        $req = $this->getRequest();
        $message = $req->getPost("message");
        if (!$this->failureHelper->report(FailureHelper::TYPE_BACKEND_ERROR, $message)) {
            throw new InternalServerException(
                "Error could not have been reported to the admin because of an internal server error."
            );
        }

        $this->sendSuccessResponse("Error was reported.");
    }
}
