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
     * Get path to job configuration Yaml file.
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return string path
     */
    private function getJobConfigPath(Submission $submission): string
    {
        $dir = self::augmentDir(self::JOB_CONFIGS, $submission);
        $type = $submission::JOB_TYPE;
        $id = $submission->getId();
        return "$dir/${type}_${id}.yml";
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
     * @param AssignmentSolution|ReferenceExerciseSolution $solution
     * @return string path
     */
    private function getSolutionArchivePath($solution): string
    {
        $dir = self::augmentDir(self::SOLUTIONS, $solution->getSolution());
        $id = $solution->getId();
        return "$dir/${id}.zip";
    }

    /**
     * Retrieve a solution file or the entire archive.
     * @param AssignmentSolution|ReferenceExerciseSolution $solution
     * @param string|null $file name of the file to be retrieved; entire archive is retrievd if null
     */
    public function getSolutionFile($solution, string $file = null): ?IImmutableFile
    {
        $path = $this->getSolutionArchivePath($solution);
        $path = $file ? "$path#$file" : $path;
        return $this->fileStorage->fetch($path);
    }

    /**
     * Remove the archive with solution source files.
     * @param AssignmentSolution|ReferenceExerciseSolution $solution
     */
    public function deleteSolutionArchive($solution): void
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
        return "$dir/${type}_$id.zip";
    }

    // FIXME -- function that moves results archive from upload tmp dir to its persistent location

    /**
     * Retrieve results archive for particular submission (works both for assignment and reference submissions).
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @return IImmutableFile|null the zip file or null, if the file does not exist
     */
    public function getResultsArchive(Submission $submission): ?IImmutableFile
    {
        $path = $this->getResultsArchivePath($submission);
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
}
