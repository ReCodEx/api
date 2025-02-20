<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
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
     * Retrieves actual submission repository based on given job type.
     * @return AssignmentSolutionSubmissions|ReferenceSolutionSubmissions|null
     */
    private function getSubmissionRepositoryByType($type)
    {
        $submissionsRepositories = [
            ReferenceSolutionSubmission::JOB_TYPE => $this->referenceSolutionSubmissions,
            AssignmentSolution::JOB_TYPE => $this->submissions,
        ];
        return array_key_exists($type, $submissionsRepositories) ? $submissionsRepositories[$type] : null;
    }

    /**
     * Create a failure report and save it.
     * @param JobId $job
     * @param string $message
     */
    private function reportFailure(JobId $job, string $message)
    {
        $type = $job->getType();
        $submissionRepository = $this->getSubmissionRepositoryByType($type);
        if (!$submissionRepository) {
            return;
        }
        
        $failureReport = SubmissionFailure::create(SubmissionFailure::TYPE_EVALUATION_FAILURE, $message);

        $submission = $submissionRepository->findOrThrow($job->getId());
        $submission->setFailure($failureReport);
        $this->submissionFailures->persist($failureReport);
        $submissionRepository->persist($submission);
        $this->failureHelper->reportSubmissionFailure($submission, FailureHelper::TYPE_BACKEND_ERROR);
    }

    /**
     * Broker reported successful completion of a job, lets store the results.
     * @param JobId $job
     */
    private function processJobCompletion(JobId $job)
    {
        $type = $job->getType();
        $submissionRepository = $this->getSubmissionRepositoryByType($type);
        if (!$submissionRepository) {
            return;
        }

        $submission = $submissionRepository->findOrThrow($job->getId());
        if (!$this->evaluationLoadingHelper->loadEvaluation($submission)) {
            $reportMessage = "Broker reports job {$job->getId()} (type: '{$job->getType()}') completion, but job results file is missing.";
            $this->reportFailure($job, $reportMessage);
            return;
        }

        if ($type === AssignmentSolution::JOB_TYPE) {
            // in case of student jobs, send notification to the user about evaluation of the solution
            $this->submissionEmailsSender->submissionEvaluated($submission);
        }
    }

    /**
     * Broker reported job failure, let's save the message.
     * @param JobId $job
     */
    private function processJobFailure(JobId $job)
    {
        $message = $this->getRequest()->getPost("message") ?: "";
        $reportMessage = "Broker reports job {$job->getId()} (type: '{$job->getType()}') processing failure: $message";
        $this->reportFailure($job, $message);
    }

    /**
     * Update the status of a job (meant to be called by the backend)
     * @POST
     * @throws InternalServerException
     * @throws NotFoundException
     * @throws InvalidStateException
     */
    #[Post("status", new VString(), "The new status of the job")]
    #[Post("message", new VString(), "A textual explanation of the status change", required: false)]
    #[Path("jobId", new VString(), "Identifier of the job whose status is being reported", required: true)]
    public function actionJobStatus($jobId)
    {
        $status = $this->getRequest()->getPost("status");

        // maps states to methods that process them
        $statusProcessors = [
            self::STATUS_OK => 'processJobCompletion',
            self::STATUS_FAILED => 'processJobFailure',
        ];

        if (array_key_exists($status, $statusProcessors)) {
            $processor = $statusProcessors[$status];
            $job = new JobId($jobId);
            $this->$processor($job);
        }

        $this->sendSuccessResponse("OK");
    }

    /**
     * Announce a backend error that is not related to any job (meant to be called by the backend)
     * @POST
     * @throws InternalServerException
     */
    #[Post("message", new VString(), "A textual description of the error")]
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
