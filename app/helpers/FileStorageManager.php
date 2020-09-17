<?php

namespace App\Helpers;

use App\Helpers\FileStorage\IFileStorage;
use App\Helpers\FileStorage\IHashFileStorage;
use App\Helpers\FileStorage\IImmutableFile;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Entity\Submission;
use App\Model\Entity\Solution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\UploadedFile;
use App\Helpers\TmpFilesHelper;
use App\Exceptions\InvalidArgumentException;
use Nette\Utils\Arrays;
use Nette\Http\FileUpload;
use Nette;

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

    /** @var TmpFilesHelper */
    private $tmpFilesHelper;

    /**
     * @param array $params Injected configuration parameters.
     */
    public function __construct(IFileStorage $fileStorage, IHashFileStorage $hashStorage, TmpFilesHelper $tmp)
    {
        $this->fileStorage = $fileStorage;
        $this->hashStorage = $hashStorage;
        $this->tmpFilesHelper = $tmp;
    }

    private static function augmentDir(string $base, $entity): string
    {
        if ($entity->getSubdir()) {
            return $base . '/' . $entity->getSubdir();
        }
        return $base;
    }

    /**
     * Get path to temporary uploaded file.
     * @param UploadedFile $file uploaded file DB entity with file metadata
     */
    private function getUploadedFilePath(UploadedFile $file): string
    {
        $dir = self::UPLOADS;
        $id = $file->getId();
        $name = $file->getName();
        return "$dir/${id}_$name";
    }

    /**
     * Store uploaded file data for associated UploadedFile db record.
     * @param UploadedFile $fileRecord database entity that corresponds to the uploaded file
     * @param FileUpload $fileData wrapper of the actual uploaded file to be saved
     * @throws InvalidArgumentException
     * @throws FileStorageException
     */
    public function storeUploadedFile(UploadedFile $fileRecord, FileUpload $fileData)
    {
        $path = $this->getUploadedFilePath($fileRecord);
        if (!$fileData->isOk()) {
            throw new InvalidArgumentException("fileData", "File was not uploaded successfully");
        }

        $this->fileStorage->storeFile($fileData->getTemporaryFile(), $path); // move, no overwrite
    }

    /**
     * Move uploaded file to persistent hash storage for supplementary files.
     * @param UploadedFile $uploadedFile to be moved from tmp upload storage to hash storage
     * @return string hash identifying stored supplementary file
     */
    public function storeUploadedSupplementaryFile(UploadedFile $uploadedFile): string
    {
        $tmp = $this->tmpFilesHelper->createTmpFile('rexfsm');
        $this->fileStorage->extract($this->getUploadedFilePath($uploadedFile), $tmp);
        return $this->hashStorage->storeFile($tmp);
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
