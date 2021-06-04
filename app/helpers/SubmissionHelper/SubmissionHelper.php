<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\ParseException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseCompilationSoftException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\FileStorage\FileStorageException;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\Solution;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\SubmissionFailure;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Solutions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;
use ZMQSocketException;
use Exception;
use Nette\Http\IResponse;

/**
 * Class which should create submission, generate job configuration,
 * store it and at the end submit solution to backend.
 */
class SubmissionHelper
{
    /** @var BackendSubmitHelper */
    private $backendSubmitHelper;

    /** @var AssignmentSolutions */
    private $assignmentSolutions;

    /** @var AssignmentSolutionSubmissions  */
    private $assignmentSubmissions;

    /** @var ReferenceSolutionSubmissions */
    public $referenceSubmissions;

    /** @var SubmissionFailures */
    private $submissionFailures;

    /** @var FailureHelper */
    private $failureHelper;

    /** @var JobConfigGenerator */
    private $jobConfigGenerator;

    /**
     * SubmissionHelper constructor.
     * @param BackendSubmitHelper $backendSubmitHelper
     */
    public function __construct(
        BackendSubmitHelper $backendSubmitHelper,
        AssignmentSolutions $assignmentSolutions,
        AssignmentSolutionSubmissions $assignmentSubmissions,
        ReferenceSolutionSubmissions $referenceSubmissions,
        SubmissionFailures $submissionFailures,
        FailureHelper $failureHelper,
        JobConfigGenerator $jobConfigGenerator
    ) {
        $this->backendSubmitHelper = $backendSubmitHelper;
        $this->assignmentSolutions = $assignmentSolutions;
        $this->assignmentSubmissions = $assignmentSubmissions;
        $this->referenceSubmissions = $referenceSubmissions;
        $this->submissionFailures = $submissionFailures;
        $this->failureHelper = $failureHelper;
        $this->jobConfigGenerator = $jobConfigGenerator;
    }

    /**
     * @param string $jobId
     * @param string $jobType
     * @param string $environment
     * @param JobConfig $jobConfig
     * @param null|string $hwgroup
     * @throws SubmissionFailedException
     * @throws InvalidStateException
     * @throws ZMQSocketException
     */
    private function internalSubmit(
        string $jobId,
        string $jobType,
        string $environment,
        JobConfig $jobConfig,
        ?string $hwgroup = null
    ): void {
        $res = $this->backendSubmitHelper->initiateEvaluation(
            $jobConfig,
            ['env' => $environment],
            $hwgroup
        );
        if (!$res) {
            throw new SubmissionFailedException("The broker rejected our request");
        }
    }

    /**
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @param Exception $exception
     * @param string $failureType
     * @param string $reportType
     * @param bool $sendEmail
     * @throws Exception
     */
    private function submissionFailed(
        $submission,
        Exception $exception,
        string $failureType = SubmissionFailure::TYPE_BROKER_REJECT,
        string $reportType = FailureHelper::TYPE_BACKEND_ERROR,
        bool $sendEmail = true,
        bool $refSolution = false
    ) {
        $failure = SubmissionFailure::create($failureType, $exception->getMessage());
        $submission->setFailure($failure);
        $this->submissionFailures->persist($failure);
        if ($refSolution) {
            $this->referenceSubmissions->persist($submission);
        } else {
            $this->assignmentSubmissions->persist($submission);
        }

        if ($sendEmail) {
            $designation = $refSolution ? 'Reference submission' : 'Submission';
            $reportMessage = "$designation '{$submission->getId()}' errored - {$exception->getMessage()}";
            $this->failureHelper->report($reportType, $reportMessage);
        }
        throw $exception; // rethrow
    }

    /**
     * Take a complete submission entity and submit it to the backend
     * @param AssignmentSolution $solution that holds the files and everything
     * @param User $user who (re)submits the solution
     * @param bool $isDebug
     * @return array tuple containing created entities [ AssignmentSolutionSubmission, JobConfig ]
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws ParseException
     * @throws Exception
     */
    public function submit(AssignmentSolution $solution, User $user, bool $isDebug = false): array
    {
        if ($solution->getId() === null) {
            throw new InvalidArgumentException("The submission object is missing an id");
        }

        // check for the license of instance of user
        $assignment = $solution->getAssignment();
        if ($assignment->getGroup() && $assignment->getGroup()->hasValidLicence() === false) {
            throw new ForbiddenRequestException(
                "Your institution does not have a valid licence and you cannot submit solutions for any assignment in this group '{$assignment->getGroup()->getId()}'. Contact your supervisor for assistance.",
                IResponse::S402_PAYMENT_REQUIRED
            );
        }

        // generate job configuration
        $compilationParams = CompilationParams::create(
            $solution->getSolution()->getFileNames(),
            $isDebug,
            $solution->getSolution()->getSolutionParams()
        );

        // create submission entity
        $submission = new AssignmentSolutionSubmission($solution, $user, $isDebug);
        $this->assignmentSubmissions->persist($submission);
        $solution->setLastSubmission($submission);
        $this->assignmentSolutions->persist($solution);

        try {
            $jobConfig = $this->jobConfigGenerator->generateJobConfig(
                $submission,
                $solution->getAssignment(),
                $solution->getSolution()->getRuntimeEnvironment(),
                $compilationParams
            );
        } catch (ExerciseConfigException | ExerciseCompilationException | FileStorageException $e) {
            $failureType = $e instanceof ExerciseCompilationSoftException ?
                SubmissionFailure::TYPE_SOFT_CONFIG_ERROR : SubmissionFailure::TYPE_CONFIG_ERROR;
            $sendEmail = $e instanceof ExerciseCompilationSoftException ? false : true;

            $this->submissionFailed($submission, $e, $failureType, FailureHelper::TYPE_API_ERROR, $sendEmail);
            // this return is here just to fool static analysis,
            // submissionFailed method throws an exception and therefore following return is never reached
            return [];
        }

        // initiate submission
        try {
            $this->internalSubmit(
                $submission->getId(),
                AssignmentSolution::JOB_TYPE,
                $solution->getSolution()->getRuntimeEnvironment()->getId(),
                $jobConfig
            );
        } catch (Exception $e) {
            $this->submissionFailed($submission, $e); // rethrows the exception
        }

        // If the submission was accepted we now have the URL where to look for the results later -> persist it
        $this->assignmentSubmissions->persist($submission);
        return [ $submission, $jobConfig ];
    }

    /**
     * @param ReferenceExerciseSolution $referenceSolution
     * @param HardwareGroup $hwGroup
     * @param User $user
     * @param bool $isDebug
     * @return array [ ReferenceSolutionSubmission, JobConfig ]
     * @throws ForbiddenRequestException
     * @throws ParseException
     * @throws Exception
     */
    public function submitReference(
        ReferenceExerciseSolution $referenceSolution,
        HardwareGroup $hwGroup,
        User $user,
        bool $isDebug = false
    ): array {
        $compilationParams = CompilationParams::create(
            $referenceSolution->getSolution()->getFileNames(),
            $isDebug,
            $referenceSolution->getSolution()->getSolutionParams()
        );

        // create the entity and generate the ID
        $submission = new ReferenceSolutionSubmission(
            $referenceSolution,
            $hwGroup,
            $user,
            $isDebug
        );
        $this->referenceSubmissions->persist($submission);

        try {
            $jobConfig = $this->jobConfigGenerator->generateJobConfig(
                $submission,
                $referenceSolution->getExercise(),
                $referenceSolution->getSolution()->getRuntimeEnvironment(),
                $compilationParams
            );
        } catch (ExerciseConfigException | ExerciseCompilationException | FileStorageException $e) {
            $failureType = $e instanceof ExerciseCompilationSoftException
                ? SubmissionFailure::TYPE_SOFT_CONFIG_ERROR
                : SubmissionFailure::TYPE_CONFIG_ERROR;
            $sendEmail = $e instanceof ExerciseCompilationSoftException ? false : true;

            $this->submissionFailed($submission, $e, $failureType, FailureHelper::TYPE_API_ERROR, $sendEmail, true);
            // this return is here just to fool static analysis,
            // submissionFailed method throws an exception and therefore following return is never reached
            return [];
        }

        try {
            $this->internalSubmit(
                $submission->getId(),
                ReferenceSolutionSubmission::JOB_TYPE,
                $referenceSolution->getSolution()->getRuntimeEnvironment()->getId(),
                $jobConfig,
                $hwGroup->getId(),
            );
        } catch (Exception $e) {
            $this->submissionFailed(
                $submission,
                $e,
                SubmissionFailure::TYPE_BROKER_REJECT,
                FailureHelper::TYPE_BACKEND_ERROR,
                true,
                true // ref solution
            );
            // rethrows the exception
        }

        $this->referenceSubmissions->flush();
        return [ $submission, $jobConfig ];
    }
}
