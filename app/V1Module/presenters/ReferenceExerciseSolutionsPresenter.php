<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\BadRequestException;
use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotReadyException;
use App\Exceptions\ParseException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\EntityMetadata\Solution\SolutionParams;
use App\Helpers\EvaluationLoadingHelper;
use App\Helpers\ExerciseConfig\Helper as ExerciseConfigHelper;
use App\Helpers\FailureHelper;
use App\Helpers\MonitorConfig;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Entity\Exercise;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Repository\Exercises;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\View\ReferenceExerciseSolutionViewFactory;
use App\Model\View\SolutionFilesViewFactory;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IReferenceExerciseSolutionPermissions;
use Exception;
use Tracy\ILogger;

/**
 * Endpoints for manipulation of reference solutions of exercises
 */
class ReferenceExerciseSolutionsPresenter extends BasePresenter
{
    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;

    /**
     * @var Exercises
     * @inject
     */
    public $exercises;

    /**
     * @var UploadedFiles
     * @inject
     */
    public $files;

    /**
     * @var ReferenceExerciseSolutions
     * @inject
     */
    public $referenceSolutions;

    /**
     * @var ReferenceSolutionSubmissions
     * @inject
     */
    public $referenceSubmissions;

    /**
     * @var SubmissionHelper
     * @inject
     */
    public $submissionHelper;

    /**
     * @var HardwareGroups
     * @inject
     */
    public $hardwareGroups;

    /**
     * @var RuntimeEnvironments
     * @inject
     */
    public $runtimeEnvironments;

    /**
     * @var IExercisePermissions
     * @inject
     */
    public $exerciseAcl;

    /**
     * @var JobConfigGenerator
     * @inject
     */
    public $jobConfigGenerator;

    /**
     * @var EvaluationLoadingHelper
     * @inject
     */
    public $evaluationLoadingHelper;

    /**
     * @var ExerciseConfigHelper
     * @inject
     */
    public $exerciseConfigHelper;

    /**
     * @var MonitorConfig
     * @inject
     */
    public $monitorConfig;

    /**
     * @var ReferenceExerciseSolutionViewFactory
     * @inject
     */
    public $referenceSolutionViewFactory;

    /**
     * @var SolutionFilesViewFactory
     * @inject
     */
    public $solutionFilesViewFactory;

    /**
     * @var IReferenceExerciseSolutionPermissions
     * @inject
     */
    public $referenceSolutionAcl;

    /**
     * @var SubmissionFailures
     * @inject
     */
    public $submissionFailures;

    /**
     * @var FailureHelper
     * @inject
     */
    public $failureHelper;




    /**
     * Get reference solutions for an exercise
     * @GET
     */
    #[Path("exerciseId", new VString(), "Identifier of the exercise", required: true)]
    public function actionSolutions(string $exerciseId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get details of a reference solution
     * @GET
     * @throws NotFoundException
     */
    #[Path("solutionId", new VString(), "An identifier of the solution", required: true)]
    public function actionDetail(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Update details about the ref. solution (note, etc...)
     * @POST
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Post("note", new VString(0, 65535), "A description by the author of the solution")]
    #[Path("solutionId", new VString(), "Identifier of the solution", required: true)]
    public function actionUpdate(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Delete reference solution with given identification.
     * @DELETE
     */
    #[Path("solutionId", new VString(), "identifier of reference solution", required: true)]
    public function actionDeleteReferenceSolution(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get a list of submissions for given reference solution.
     * @GET
     * @throws InternalServerException
     */
    #[Path("solutionId", new VString(), "identifier of the reference exercise solution", required: true)]
    public function actionSubmissions(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get reference solution evaluation (i.e., submission) for an exercise solution.
     * @GET
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Path("submissionId", new VString(), "identifier of the reference exercise submission", required: true)]
    public function actionSubmission(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Remove reference solution evaluation (submission) permanently.
     * @DELETE
     */
    #[Path("submissionId", new VString(), "Identifier of the reference solution submission", required: true)]
    public function actionDeleteSubmission(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Pre submit action which will, based on given files, detect possible runtime
     * environments for the exercise. Also it can be further used for entry
     * points and other important things that should be provided by user during submit.
     * @POST
     * @throws NotFoundException
     * @throws InvalidApiArgumentException
     * @throws ExerciseConfigException
     * @throws BadRequestException
     */
    #[Post("files", new VArray())]
    #[Path("exerciseId", new VString(), "identifier of exercise", required: true)]
    public function actionPreSubmit(string $exerciseId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Add new reference solution to an exercise
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws SubmissionEvaluationFailedException
     * @throws ParseException
     * @throws BadRequestException
     */
    #[Post("note", new VString(), "Description of this particular reference solution, for example used algorithm")]
    #[Post("files", new VMixed(), "Files of the reference solution", nullable: true)]
    #[Post("runtimeEnvironmentId", new VMixed(), "ID of runtime for this solution", nullable: true)]
    #[Post("solutionParams", new VMixed(), "Solution parameters", required: false, nullable: true)]
    #[Path("exerciseId", new VString(), "Identifier of the exercise", required: true)]
    public function actionSubmit(string $exerciseId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Evaluate a single reference exercise solution for all configured hardware groups
     * @POST
     * @throws ForbiddenRequestException
     * @throws ParseException
     * @throws BadRequestException
     */
    #[Post("debug", new VBool(), "Debugging evaluation with all logs and outputs", required: false)]
    #[Path("id", new VUuid(), "Identifier of the reference solution", required: true)]
    public function actionResubmit(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Evaluate all reference solutions for an exercise (and for all configured hardware groups).
     * @POST
     * @throws ForbiddenRequestException
     * @throws ParseException
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Post("debug", new VBool(), "Debugging evaluation with all logs and outputs", required: false)]
    #[Path("exerciseId", new VString(), "Identifier of the exercise", required: true)]
    public function actionResubmitAll($exerciseId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * @param ReferenceExerciseSolution $referenceSolution
     * @param bool $isDebug
     * @return array
     * @throws ForbiddenRequestException
     * @throws ParseException
     * @throws Exception
     */
    private function finishSubmission(
        ReferenceExerciseSolution $referenceSolution,
        bool $isDebug = false
    ): array {
        $submissions = [];
        $errors = [];

        $hwGroups = $referenceSolution->getExercise()->getHardwareGroups();
        foreach ($hwGroups->getValues() as $hwGroup) {
            try {
                [$submission, $jobConfig] = $this->submissionHelper->submitReference(
                    $referenceSolution,
                    $hwGroup,
                    $this->getCurrentUser(),
                    $isDebug
                );

                $submissions[] = [
                    "submission" => $submission,
                    "webSocketChannel" => [
                        "id" => $jobConfig->getJobId(),
                        "monitorUrl" => $this->monitorConfig->getAddress(),
                        "expectedTasksCount" => $jobConfig->getTasksCount()
                    ]
                ];
            } catch (ExerciseConfigException | ExerciseCompilationException | FileStorageException $e) {
                $this->logger->log("Reference evaluation exception: " . $e->getMessage(), ILogger::EXCEPTION);
                throw $e; // unrecoverable errors
            } catch (Exception $e) {
                $this->logger->log("Reference evaluation exception: " . $e->getMessage(), ILogger::EXCEPTION);
                $errors[$hwGroup->getId()] = $e->getMessage();
            }
        }

        if (count($errors) > 0) {
            $this->referenceSubmissions->flush();
            $this->referenceSolutions->refresh($referenceSolution); // it would be tedious update the entity manually
        }

        return [
            "referenceSolution" => $this->referenceSolutionViewFactory->getReferenceSolution($referenceSolution),
            "submissions" => $submissions,
            "errors" => $errors
        ];
    }


    /**
     * Download archive containing all solution files for particular reference solution.
     * @GET
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    #[Path("solutionId", new VString(), "of reference solution", required: true)]
    public function actionDownloadSolutionArchive(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get the list of submitted files of the solution.
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "of reference solution", required: true)]
    public function actionFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Download result archive from backend for a reference solution evaluation
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws NotReadyException
     * @throws InternalServerException
     * @throws \Nette\Application\AbortException
     */
    #[Path("submissionId", new VString(), required: true)]
    public function actionDownloadResultArchive(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Get score configuration associated with given submission evaluation
     * @GET
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Path("submissionId", new VString(), "identifier of the reference exercise submission", required: true)]
    public function actionEvaluationScoreConfig(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Set visibility of given reference solution.
     * @POST
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     */
    #[Post("visibility", new VInt(), "New visibility level.", required: true)]
    #[Path("solutionId", new VString(), "of reference solution", required: true)]
    public function actionSetVisibility(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }
}
