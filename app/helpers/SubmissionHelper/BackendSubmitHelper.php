<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Exceptions\SubmissionFailedException;
use App\Helpers\JobConfig\JobConfig;
use ZMQSocketException;

/**
 * Easy submit new job to the backend. This means prepare the archive, save it to the
 * file storage and then tell broker to evaluate the submission.
 */
class BackendSubmitHelper
{

    /** @var BrokerProxy */
    private $broker;

    /** @var FileStorageManager */
    private $fileStorage;

    /**
     * Constructor
     * @param BrokerProxy $broker Initialized communication wrapper with broker
     * @param FileStorageManager $fileStorage the manager that handles all file transactions
     */
    public function __construct(BrokerProxy $broker, FileStorageManager $fileStorage)
    {
        $this->broker = $broker;
        $this->fileStorage = $fileStorage;
    }

    /**
     * Upload the files to the fileserver and initiates evaluation on backend
     * @param JobConfig $jobConfig The submission configuration file content
     * @param array $headers Headers used to further specify which workers can evaluate the submission
     * @param string $hardwareGroup Hardware group to evaluate this submission with
     *                              (if none is given, all hardware groups associated with the assignment can be used)
     * @return bool true if the submission was accepted and evaluation started, otherwise false
     * @throws SubmissionFailedException
     * @throws InvalidStateException
     * @throws ZMQSocketException
     */
    public function initiateEvaluation(
        JobConfig $jobConfig,
        array $headers = [],
        string $hardwareGroup = null
    ): bool {
        // no files preparations are necessary, the submission archive can be constructed on demand
        $archiveUrl = $this->fileStorage->getWorkerSubmissionExternalUrl($jobConfig->getJobType(), $jobConfig->getId());
        $resultsUrl = $this->fileStorage->getWorkerResultExternalUrl($jobConfig->getJobType(), $jobConfig->getId());

        // tell broker that we have new job which has to be executed
        return $this->broker->startEvaluation(
            $jobConfig->getJobId(),
            $hardwareGroup !== null ? [$hardwareGroup] : $jobConfig->getHardwareGroups(),
            $headers,
            $archiveUrl,
            $resultsUrl
        );
    }
}
