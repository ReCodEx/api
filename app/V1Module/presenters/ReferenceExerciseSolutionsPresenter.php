<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseCompilationSoftException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotReadyException;
use App\Exceptions\ParseException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\EntityMetadata\Solution\SolutionParams;
use App\Helpers\EvaluationLoadingHelper;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Helper as ExerciseConfigHelper;
use App\Helpers\FailureHelper;
use App\Helpers\MonitorConfig;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Entity\Exercise;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\SubmissionFailure;
use App\Model\Entity\UploadedFile;
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


    public function noncheckSolutions(string $exerciseId)
    {
        $exercise = $this->exercises->findOrThrow($exerciseId);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You cannot access this exercise solutions");
        }
    }

    /**
     * Get reference solutions for an exercise
     * @GET
     * @param string $exerciseId Identifier of the exercise
     */
    public function actionSolutions(string $exerciseId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDetail(string $solutionId)
    {
        $solution = $this->referenceSolutions->findOrThrow($solutionId);
        if (!$this->referenceSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get details of a reference solution
     * @GET
     * @param string $solutionId An identifier of the solution
     * @throws NotFoundException
     */
    public function actionDetail(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdate(string $solutionId)
    {
        $solution = $this->referenceSolutions->findOrThrow($solutionId);
        if (!$this->referenceSolutionAcl->canUpdate($solution)) {
            throw new ForbiddenRequestException("You cannot update the ref. solution");
        }
    }

    /**
     * Update details about the ref. solution (note, etc...)
     * @POST
     * @Param(type="post", name="note", validation="string:0..65535",
     *        description="A description by the author of the solution")
     * @param string $solutionId Identifier of the solution
     * @throws NotFoundException
     * @throws InternalServerException
     */
    public function actionUpdate(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteReferenceSolution(string $solutionId)
    {
        $solution = $this->referenceSolutions->findOrThrow($solutionId);
        if (!$this->referenceSolutionAcl->canDelete($solution)) {
            throw new ForbiddenRequestException("You cannot delete reference solution of this exercise");
        }
    }

    /**
     * Delete reference solution with given identification.
     * @DELETE
     * @param string $solutionId identifier of reference solution
     */
    public function actionDeleteReferenceSolution(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSubmissions(string $solutionId)
    {
        $solution = $this->referenceSolutions->findOrThrow($solutionId);
        if (!$this->referenceSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot access this reference solution submissions");
        }
    }

    /**
     * Get a list of submissions for given reference solution.
     * @GET
     * @param string $solutionId identifier of the reference exercise solution
     * @throws InternalServerException
     */
    public function actionSubmissions(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSubmission(string $submissionId)
    {
        $submission = $this->referenceSubmissions->findOrThrow($submissionId);
        $solution = $submission->getReferenceSolution();
        if (!$this->referenceSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot access this exercise evaluations");
        }
    }

    /**
     * Get reference solution evaluation (i.e., submission) for an exercise solution.
     * @GET
     * @param string $submissionId identifier of the reference exercise submission
     * @throws NotFoundException
     * @throws InternalServerException
     */
    public function actionSubmission(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteSubmission(string $submissionId)
    {
        $submission = $this->referenceSubmissions->findOrThrow($submissionId);
        $solution = $submission->getReferenceSolution();
        if (!$this->referenceSolutionAcl->canDeleteEvaluation($solution)) {
            throw new ForbiddenRequestException("You cannot delete this submission");
        }
        if ($solution->getSubmissions()->count() < 2) {
            throw new BadRequestException("You cannot delete last submission of a solution");
        }
    }

    /**
     * Remove reference solution evaluation (submission) permanently.
     * @DELETE
     * @param string $submissionId Identifier of the reference solution submission
     */
    public function actionDeleteSubmission(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckPreSubmit(string $exerciseId)
    {
        $exercise = $this->exercises->findOrThrow($exerciseId);
        if (!$this->exerciseAcl->canAddReferenceSolution($exercise)) {
            throw new ForbiddenRequestException("You cannot create reference solutions for this exercise");
        }
    }

    /**
     * Pre submit action which will, based on given files, detect possible runtime
     * environments for the exercise. Also it can be further used for entry
     * points and other important things that should be provided by user during submit.
     * @POST
     * @param string $exerciseId identifier of exercise
     * @Param(type="post", name="files", validation="array", "Array of identifications of submitted files")
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws ExerciseConfigException
     * @throws BadRequestException
     */
    public function actionPreSubmit(string $exerciseId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSubmit(string $exerciseId)
    {
        $exercise = $this->exercises->findOrThrow($exerciseId);
        if (!$this->exerciseAcl->canAddReferenceSolution($exercise)) {
            throw new ForbiddenRequestException("You cannot create reference solutions for this exercise");
        }
    }

    /**
     * Add new reference solution to an exercise
     * @POST
     * @Param(type="post", name="note", validation="string",
     *        description="Description of this particular reference solution, for example used algorithm")
     * @Param(type="post", name="files", description="Files of the reference solution")
     * @Param(type="post", name="runtimeEnvironmentId", description="ID of runtime for this solution")
     * @Param(type="post", name="solutionParams", required=false, description="Solution parameters")
     * @param string $exerciseId Identifier of the exercise
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws SubmissionEvaluationFailedException
     * @throws ParseException
     * @throws BadRequestException
     */
    public function actionSubmit(string $exerciseId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckResubmit(string $id)
    {
        /** @var ReferenceExerciseSolution $referenceSolution */
        $referenceSolution = $this->referenceSolutions->findOrThrow($id);

        if (!$this->referenceSolutionAcl->canEvaluate($referenceSolution)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Evaluate a single reference exercise solution for all configured hardware groups
     * @POST
     * @param string $id Identifier of the reference solution
     * @Param(type="post", name="debug", validation="bool", required=false,
     *        description="Debugging evaluation with all logs and outputs")
     * @throws ForbiddenRequestException
     * @throws ParseException
     * @throws BadRequestException
     */
    public function actionResubmit(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckResubmitAll($exerciseId)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($exerciseId);
        $solutions = array_filter(
            $exercise->getReferenceSolutions()->getValues(),
            function ($solution) {
                return $this->referenceSolutionAcl->canViewDetail($solution);
            }
        );

        foreach ($solutions as $referenceSolution) {
            if (!$this->referenceSolutionAcl->canEvaluate($referenceSolution)) {
                throw new ForbiddenRequestException();
            }
        }
    }

    /**
     * Evaluate all reference solutions for an exercise (and for all configured hardware groups).
     * @POST
     * @param string $exerciseId Identifier of the exercise
     * @Param(type="post", name="debug", validation="bool", required=false,
     *        description="Debugging evaluation with all logs and outputs")
     * @throws ForbiddenRequestException
     * @throws ParseException
     * @throws BadRequestException
     * @throws NotFoundException
     */
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
                [ $submission, $jobConfig ] = $this->submissionHelper->submitReference(
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

    public function noncheckDownloadSolutionArchive(string $solutionId)
    {
        $solution = $this->referenceSolutions->findOrThrow($solutionId);
        if (!$this->referenceSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot access archive of reference solution files");
        }
    }

    /**
     * Download archive containing all solution files for particular reference solution.
     * @GET
     * @param string $solutionId of reference solution
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    public function actionDownloadSolutionArchive(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckFiles(string $id)
    {
        $solution = $this->referenceSolutions->findOrThrow($id);
        if (!$this->referenceSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot access the reference solution files metadata");
        }
    }

    /**
     * Get the list of submitted files of the solution.
     * @GET
     * @param string $id of reference solution
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownloadResultArchive(string $submissionId)
    {
        /** @var ReferenceSolutionSubmission $submission */
        $submission = $this->referenceSubmissions->findOrThrow($submissionId);
        $refSolution = $submission->getReferenceSolution();

        if (!$this->referenceSolutionAcl->canViewDetail($refSolution)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Download result archive from backend for a reference solution evaluation
     * @GET
     * @param string $submissionId
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws NotReadyException
     * @throws InternalServerException
     * @throws \Nette\Application\AbortException
     */
    public function actionDownloadResultArchive(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckEvaluationScoreConfig(string $submissionId)
    {
        $submission = $this->referenceSubmissions->findOrThrow($submissionId);
        if (!$this->referenceSolutionAcl->canViewDetail($submission->getReferenceSolution())) {
            throw new ForbiddenRequestException("You cannot access this exercise evaluations");
        }
    }

    /**
     * Get score configuration associated with given submission evaluation
     * @GET
     * @param string $submissionId identifier of the reference exercise submission
     * @throws NotFoundException
     * @throws InternalServerException
     */
    public function actionEvaluationScoreConfig(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetVisibility(string $solutionId)
    {
        $solution = $this->referenceSolutions->findOrThrow($solutionId);
        if (!$this->referenceSolutionAcl->canSetVisibility($solution)) {
            throw new ForbiddenRequestException("You cannot change visibility of given reference solution");
        }
    }

    /**
     * Set visibility of given reference solution.
     * @POST
     * @param string $solutionId of reference solution
     * @Param(type="post", name="visibility", required=true, validation="numericint",
     *        description="New visibility level.")
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     */
    public function actionSetVisibility(string $solutionId)
    {
        $this->sendSuccessResponse("OK");
    }
}
