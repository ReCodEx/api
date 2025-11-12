<?php

namespace App\Helpers\JobConfig;

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\FileStorage\FileStorageException;
use App\Helpers\Evaluation\IExercise;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Compiler;
use App\Helpers\FileStorageManager;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\Submission;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;

/**
 * Wrapper around compiler of exercise configuration to job configuration
 * which handles storing of job configuration on persistent data storage.
 */
class Generator
{
    /**
     * @var FileStorageManager
     */
    private $fileStorage;

    /**
     * @var Compiler
     */
    private $compiler;


    /**
     * Generator constructor.
     * @param FileStorageManager $fileStorage
     * @param Compiler $compiler
     */
    public function __construct(FileStorageManager $fileStorage, Compiler $compiler)
    {
        $this->fileStorage = $fileStorage;
        $this->compiler = $compiler;
    }

    /**
     * Generate job configuration from exercise configuration and save it in the file storage.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @param IExercise $exerciseAssignment
     * @param RuntimeEnvironment $runtimeEnvironment
     * @param CompilationParams $params
     * @return JobConfig
     * @throws ExerciseConfigException
     * @throws ExerciseCompilationException
     * @throws FileStorageException
     */
    public function generateJobConfig(
        Submission $submission,
        IExercise $exerciseAssignment,
        RuntimeEnvironment $runtimeEnvironment,
        CompilationParams $params
    ): JobConfig {
        $jobConfig = $this->compiler->compile($exerciseAssignment, $runtimeEnvironment, $params);
        $jobConfig->getSubmissionHeader()->setId($submission->getId())->setType($submission::JOB_TYPE);
        $jobConfig->setFileCollector($this->fileStorage->getWorkerExerciseFilesExternalUrlPrefix());
        $this->fileStorage->storeJobConfig($submission, (string)$jobConfig);
        return $jobConfig;
    }
}
