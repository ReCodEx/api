<?php

namespace App\Helpers;

use App\Helpers\FileStorage\IFileStorage;
use App\Helpers\FileStorage\IHashFileStorage;
use App\Helpers\FileStorage\IImmutableFile;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Entity\Submission;
use App\Model\Entity\Solution;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AttachmentFile;
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
    private const JOB_RESULT_FILENAME = 'result/result.yml';

    /** @var IFileStorage */
    private $fileStorage;
    
    /** @var IHashFileStorage */
    private $hashStorage;

    /** @var TmpFilesHelper */
    private $tmpFilesHelper;

    /** @var string */
    private $apiUrl;

    /**
     * Initialize the manager by injecting dependencies and configuration
     * @param IFileStorage $fileStorage
     * @param IHashFileStorage $hashStorage
     * @param TmpFilesHelper $tmp
     * @param string $apiUrl injected configuration containing external URL to API (prefix for generated external URLs)
     */
    public function __construct(
        IFileStorage $fileStorage,
        IHashFileStorage $hashStorage,
        TmpFilesHelper $tmp,
        string $apiUrl
    ) {
        $this->fileStorage = $fileStorage;
        $this->hashStorage = $hashStorage;
        $this->tmpFilesHelper = $tmp;
        $this->apiUrl = $apiUrl;
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
     * @return string path
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
     * Retrieve an plain uploaded file (in tmp storage).
     * @param UploadedFile $file corresponding database entity
     * @return IImmutableFile|null
     */
    public function getUploadedFile(UploadedFile $file): ?IImmutableFile
    {
        $path = $this->getUploadedFilePath($file);
        return $this->fileStorage->fetch($path);
    }

    /**
     * Remove uploaded file.
     * @param UploadedFile $file corresponding database entity
     */
    public function deleteUploadedFile(UploadedFile $file): void
    {
        $path = $this->getUploadedFilePath($file);
        $this->fileStorage->delete($path);
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
     * Get path to attachment file within the file storage.
     * @param AttachmentFile $file
     * @return string path
     */
    private function getAttachmentFilePath(AttachmentFile $file): string
    {
        $dir = self::ATTACHMENTS;
        $user = $file->getUserIdEvenIfDeleted();
        $id = $file->getId();
        $name = $file->getName();
        return "$dir/user_$user/${id}_${name}";
    }

    /**
     * Move uploaded file to persistent hash storage for supplementary files.
     * @param AttachmentFile $file newly created attachment file (from uploaded file) entity
     */
    public function storeUploadedAttachmentFile(AttachmentFile $file): void
    {
        $oldPath = $this->getUploadedFilePath($file);
        $newPath = $this->getAttachmentFilePath($file);
        $this->fileStorage->move($oldPath, $newPath);
    }

    /**
     * Retrieve an atttachment file (file attached to specification of an exercise/assignment).
     * @param AttachmentFile $file
     * @return IImmutableFile|null a file object or null if no such file exists
     */
    public function getAttachmentFile(AttachmentFile $file): ?IImmutableFile
    {
        $path = $this->getAttachmentFilePath($file);
        return $this->fileStorage->fetch($path);
    }

    /**
     * Remove an atttachment file (file attached to specification of an exercise/assignment).
     * @param AttachmentFile $file
     */
    public function deleteAttachmentFile(AttachmentFile $file): void
    {
        $path = $this->getAttachmentFilePath($file);
        $this->fileStorage->delete($path);
    }

    /**
     * Get path to job configuration Yaml file.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return string path
     */
    private function getJobConfigPath(Submission $submission): string
    {
        $dir = self::augmentDir(self::JOB_CONFIGS, $submission);
        $type = $submission::JOB_TYPE;
        $id = $submission->getId();
        return "$dir/${id}_${type}.yml";
    }

    /**
     * Retrieve a job config file for given submission.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     */
    public function storeJobConfig(Submission $submission, string $config): void
    {
        $path = $this->getJobConfigPath($submission);
        $this->fileStorage->storeContents($config, $path, true); // true = allow overwrite
    }

    /**
     * Retrieve a job config file for given submission.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return IImmutableFile|null a file object or null if no such file exists
     */
    public function getJobConfig(Submission $submission): ?IImmutableFile
    {
        $path = $this->getJobConfigPath($submission);
        return $this->fileStorage->fetch($path);
    }

    /**
     * Remove job config Yaml file for given submission.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     */
    public function deleteJobConfig(Submission $submission): void
    {
        $path = $this->getJobConfigPath($submission);
        $this->fileStorage->delete($path);
    }

    /**
     * Get path to a solution archive with source code files.
     * @param Solution $solution
     * @return string path
     */
    private function getSolutionArchivePath(Solution $solution): string
    {
        $dir = self::augmentDir(self::SOLUTIONS, $solution);
        $id = $solution->getId();
        return "$dir/${id}.zip";
    }

    /**
     * Move uploaded file to persistent storage of given solutions' files.
     * @param Solution $solution
     * @param UploadedFile $file
     */
    public function storeUploadedSolutionFile(Solution $solution, UploadedFile $file): void
    {
        $oldPath = $this->getUploadedFilePath($file);
        $newPath = $this->getSolutionArchivePath($solution) . '#' . $file->getName();
        $this->fileStorage->move($oldPath, $newPath);
    }

    /**
     * Retrieve a solution file or the entire archive.
     * @param Solution $solution
     * @param string|null $file name of the file to be retrieved; entire archive is retrievd if null
     */
    public function getSolutionFile(Solution $solution, string $file = null): ?IImmutableFile
    {
        $path = $this->getSolutionArchivePath($solution);
        $path = $file ? "$path#$file" : $path;
        return $this->fileStorage->fetch($path);
    }

    /**
     * Remove the archive with solution source files.
     * @param Solution $solution
     */
    public function deleteSolutionArchive(Solution $solution): void
    {
        $path = $this->getSolutionArchivePath($solution);
        $this->fileStorage->delete($path);
    }

    /**
     * Internal function that assembles relative path to submission archive.
     * @param string $type job type (reference/student)
     * @param string $id submission identifier
     * @return string relative storage path
     */
    private function getWorkerSubmissionArchivePath(string $type, string $id): string
    {
        return self::WORKER_DOWNLOADS . "/${id}_${type}.zip";
    }

    /**
     * Retrieve a ZIP archive with the submission made ready for the worker.
     * @param string $type of the submission (reference/student)
     * @param string $id of the submission
     * @return IImmutableFile|null a file object or null if no such file exists
     */
    public function getWorkerSubmissionArchive(string $type, string $id): ?IImmutableFile
    {
        $path = $this->getWorkerSubmissionArchivePath($type, $id);
        return $this->fileStorage->fetch($path);
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
        return self::WORKER_UPLOADS . "/${submissionId}_${type}.zip";
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

    /**
     * Get path to persistent location of results archive.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return string path
     */
    private function getResultsArchivePath(Submission $submission): string
    {
        $dir = self::augmentDir(self::RESULTS, $submission);
        $type = $submission::JOB_TYPE;
        $id = $submission->getId();
        return "$dir/${id}_${type}.zip";
    }

    /**
     * Internal function that makes sure the results archive is moved from uploads.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return IImmutableFile|null if the file is found, return its wrapper (after moving)
     */
    private function findAndMoveResultsArchiveIfNecessary(Submission $submission): ?IImmutableFile
    {
        $path = $this->getResultsArchivePath($submission);
        $file = $this->fileStorage->fetch($path);
        if ($file === null) {
            // if missing, try to find it in (and move it from) worker upload directory
            $uploadPath = $this->getWorkerUploadResultsArchivePath($submission::JOB_TYPE, $submission->getId());
            if ($this->fileStorage->fetch($uploadPath) !== null) {
                $this->fileStorage->move($uploadPath, $path);
            }
            $file = $this->fileStorage->fetch($path);
        }
        return $file;
    }

    /**
     * Retrieve results archive for particular submission (works both for assignment and reference submissions).
     * If the results are missing, an attempt to fetch it from upload directory is performed.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return IImmutableFile|null the zip file or null, if the file does not exist
     */
    public function getResultsArchive(Submission $submission): ?IImmutableFile
    {
        return $this->findAndMoveResultsArchiveIfNecessary($submission);
    }

    /**
     * Retrieve results Yaml config file from the archive.
     * If the results are missing, an attempt to fetch it from upload directory is performed.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return IImmutableFile|null the yaml file or null, if the file does not exist
     */
    public function getResultsYamlFile(Submission $submission): ?IImmutableFile
    {
        if ($this->findAndMoveResultsArchiveIfNecessary($submission) === null) {
            return null; // archive not found (not even in uploads)
        }

        $path = $this->getResultsArchivePath($submission) . '#' . self::JOB_RESULT_FILENAME;
        return $this->fileStorage->fetch($path);
    }

    /**
     * Remove a results file of a particular submission.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     */
    public function deleteResultsArchive(Submission $submission): void
    {
        $path = $this->getResultsArchivePath($submission);
        $this->fileStorage->delete($path);
    }

    /**
     * Generator for external URL passed to worker where the submission archive is located.
     * If the files are managed internally by core, this must match router configuration.
     * @param string $type job type (reference/student)
     * @param string $id submission/job identifier
     * @return string full URL
     */
    public function getWorkerSubmissionExternalUrl(string $type, string $id): string
    {
        return "$this->apiUrl/v1/worker-files/submission-archive/$type/$id";
    }

    /**
     * Generator for external URL passed to worker where the results should be uploaded.
     * If the files are managed internally by core, this must match router configuration.
     * @param string $type job type (reference/student)
     * @param string $id submission/job identifier
     * @return string full URL
     */
    public function getWorkerResultExternalUrl(string $type, string $id): string
    {
        return "$this->apiUrl/v1/worker-files/result/$type/$id";
    }

    /**
     * Generator for external URL prefixes for downloading supplementary files.
     * The worker only appends /<hash> to the URL prefix.
     * @return string URL prefix
     */
    public function getWorkerSupplementaryFilesExternalUrlPrefix(): string
    {
        return "$this->apiUrl/v1/worker-files/supplementary-file";
    }
}
