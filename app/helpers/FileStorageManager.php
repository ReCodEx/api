<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;
use App\Helpers\FileStorage\IFileStorage;
use App\Helpers\FileStorage\IHashFileStorage;
use App\Helpers\FileStorage\IImmutableFile;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Entity\Submission;
use App\Model\Entity\Solution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;

/**
 * File storage manager provides access to underlying file storages.
 * Only this entity knows how the storage is configured and internally structured.
 * The rest of the core module should use only this manager when working with files
 * (with possible exception of working with temporary files which are just being uploaded/downloaded).
 */
class FileStorageManager
{
    use Nette\SmartObject;

    private const UPLOADS = 'uploads';
    private const ATTACHMENTS = 'attachments';
    private const SOLUTIONS = 'solutions';
    private const JOB_CONFIGS = 'job_configs';
    private const RESULTS = 'results';
    private const WORKER_DOWNLOADS = 'worker_downloads';
    private const WORKER_UPLOADS = 'worker_uploads';
    private const JOB_CONFIG_FILENAME = 'job-config.yml';

    /** @var IFileStorage */
    private $fileStorage;
    
    /** @var IHashFileStorage */
    private $hashStorage;

    /**
     * @param array $params Injected configuration parameters.
     */
    public function __construct(IFileStorage $fileStorage, IHashFileStorage $hashStorage)
    {
        $this->fileStorage = $fileStorage;
        $this->hashStorage = $hashStorage;
    }

    private static function augmentDir(string $base, $entity): string
    {
        if ($entity->getSubdir()) {
            return $base . '/' . $entity->getSubdir();
        }
        return $base;
    }


    /**
     * Retrieve a supplementary file by its hash and return an immutable file object.
     * @param string $hash hash identification of the file
     * @return IImmutableFile|null a file object or null if no such file exists
     */
    public function getSupplementaryFileByHash(string $hash): ?IImmutableFile
    {
        return $this->hashStorage->fetch($hash);
    }

    /**
     * Retrieve a job config file for given submission.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return IImmutableFile|null a file object or null if no such file exists
     */
    public function getJobConfig(Submission $submission): ?IImmutableFile
    {
        $dir = self::augmentDir(self::JOB_CONFIGS, $submission);
        $type = $submission::JOB_TYPE;
        $id = $submission->getId();
        return $this->fileStorage->fetch("$dir/${type}_${id}.yml");
    }

    /**
     * Retrieve a solution file or the entire archive.
     * @param Solution $solution
     * @param string|null $file name of the file to be retrieved; entire archive is retrievd if null
     */
    public function getSolutionFile(Solution $solution, string $file = null): ?IImmutableFile
    {
        $dir = self::augmentDir(self::SOLUTIONS, $solution);
        $id = $solution->getId();
        $file = $file ? "#$file" : '';
        return $this->fileStorage->fetch("$dir/${id}.zip$file");
    }

    /**
     * Internal function that assembles relative path to submission archive.
     * @param string $type job type (reference/student)
     * @param string $id submission identifier
     * @return string relative storage path
     */
    private function getWorkerSubmissionArchivePath(string $type, string $id): string
    {
        return self::WORKER_DOWNLOADS . "/${type}_${id}.zip";
    }

    /**
     * Retrieve a ZIP archive with the submission made ready for the worker.
     * @param string $type of the submission (reference/student)
     * @param string $id of the submission
     * @return IImmutableFile|null a file object or null if no such file exists
     */
    public function getWorkerSubmissionArchive(string $type, string $id): ?IImmutableFile
    {
        return $this->fileStorage->fetch($this->getWorkerSubmissionArchivePath($type, $id));
    }

    /**
     * Create and return a ZIP archive with the submission made ready for the worker.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return IImmutableFile|null a file object or null if no such file exists
     * @throws FileStorageException
     */
    public function createWorkerSubmissionArchive(Submission $submission): ?IImmutableFile
    {
        $path = $this->getWorkerSubmissionArchivePath($submission::JOB_TYPE, $submission->getId());
        if ($this->fileStorage->fetch($path) === null) {
            $solutionArchive = $this->getSolutionFile($submission->getSolution());
            $configFile = $this->getJobConfig($submission);
            if (!$solutionArchive || !$configFile) {
                return null;
            }

            // copy the archive with source codes and inject the yaml config inside
            $this->fileStorage->copy($solutionArchive->getStoragePath(), $path);
            $this->fileStorage->copy($configFile->getStoragePath(), $path . '#' . self::JOB_CONFIG_FILENAME);
        }
        
        return $this->fileStorage->fetch($path);
    }

    /**
     * Internal function that assembles relative path to submission archive.
     * @param string $type job type (reference/student)
     * @param string $submissionId
     * @return string relative storage path
     */
    private function getWorkerUploadResultsArchivePath(string $type, string $submissionId): string
    {
        return self::WORKER_UPLOADS . "/${type}_${submissionId}.zip";
    }

    /**
     * Saves file that has been sent over in request body into file storage as uploaded result archive.
     * @param string $type job type (reference/student)
     * @param string $submissionId
     * @throws FileStorageException
     */
    public function saveUploadedResultsArchive(string $type, string $submissionId): void
    {
        $fp = fopen('php://input', 'rb');
        if (!$fp) {
            throw new FileStorageException("Unable to read request body.", 'php://input');
        }

        try {
            $this->fileStorage->storeStream($fp, $this->getWorkerUploadResultsArchivePath($type, $submissionId), true);
        } finally {
            fclose($fp);
        }
    }
}
