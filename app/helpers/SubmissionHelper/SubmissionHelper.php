<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Exceptions\SubmissionFailedException;
use App\Helpers\JobConfig\JobConfig;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\AssignmentSolution;
use ZMQSocketException;

/**
 * Class which should create submission, generate job configuration,
 * store it and at the end submit solution to backend.
 */
class SubmissionHelper
{
    /** @var BackendSubmitHelper */
    private $backendSubmitHelper;

    /**
     * SubmissionHelper constructor.
     * @param BackendSubmitHelper $backendSubmitHelper
     */
    public function __construct(BackendSubmitHelper $backendSubmitHelper)
    {
        $this->backendSubmitHelper = $backendSubmitHelper;
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
     * Submit regular (student) job.
     * @param string $jobId (aka submission ID)
     * @param string $environment
     * @param JobConfig $jobConfig
     * @throws InvalidStateException
     * @throws SubmissionFailedException
     * @throws ZMQSocketException
     */
    public function submit(
        string $jobId,
        string $environment,
        JobConfig $jobConfig
    ): void {
        $this->internalSubmit($jobId, AssignmentSolution::JOB_TYPE, $environment, $jobConfig);
    }

    /**
     * Submit reference job.
     * @param string $jobId (aka submission ID)
     * @param string $environment
     * @param null|string $hwgroup
     * @param JobConfig $jobConfig
     * @throws InvalidStateException
     * @throws SubmissionFailedException
     * @throws ZMQSocketException
     */
    public function submitReference(
        string $jobId,
        string $environment,
        ?string $hwgroup,
        JobConfig $jobConfig
    ): void {
        $this->internalSubmit(
            $jobId,
            ReferenceSolutionSubmission::JOB_TYPE,
            $environment,
            $jobConfig,
            $hwgroup
        );
    }
}
