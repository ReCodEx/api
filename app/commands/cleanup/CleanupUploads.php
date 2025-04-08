<?php

namespace App\Console;

use App\Helpers\UploadsConfig;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Repository\BaseRepository;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\UploadedPartialFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\ILogger;
use DateTime;

class CleanupUploads extends Command
{
    protected static $defaultName = 'db:cleanup:uploads';

    /**
     * @var UploadsConfig
     */
    private $uploadsConfig;

    /**
     * @var UploadedFiles
     */
    private $uploadedFiles;

    /**
     * @var UploadedPartialFiles
     */
    private $uploadedPartialFiles;

    /**
     * @var ILogger
     */
    private $logger;

    /**
     * @var FileStorageManager
     */
    private $fileStorage;


    public function __construct(
        UploadsConfig $config,
        UploadedFiles $uploadedFiles,
        UploadedPartialFiles $uploadedPartialFiles,
        ILogger $logger,
        FileStorageManager $fileStorage
    ) {
        parent::__construct();
        $this->uploadsConfig = $config;
        $this->uploadedFiles = $uploadedFiles;
        $this->uploadedPartialFiles = $uploadedPartialFiles;
        $this->logger = $logger;
        $this->fileStorage = $fileStorage;
    }

    protected function configure()
    {
        $this->setName('db:cleanup:uploads')
            ->setDescription('Remove unused uploaded files and corresponding DB records.');
    }

    /**
     * Wrapper function for deleting a list of files, computing basic stats, and printing out job results.
     * @param array $files list of files to be deleted
     * @param BaseRepository $fileRepository related repository from which the $files entities are
     * @param OutputInterface $output console access for printing the info
     * @param callable $deleteFile the actual function that can delete the physical file
     */
    protected function removeOldFiles(
        array $files,
        BaseRepository $fileRepository,
        OutputInterface $output,
        callable $deleteFile
    ) {
        $deleted = 0;
        $missing = 0;
        $errors = 0;

        foreach ($files as $file) {
            try {
                if ($deleteFile($file)) {
                    ++$deleted;
                } else {
                    ++$missing;
                }
            } catch (FileStorageException $e) {
                $this->logger->log($e->getMessage(), ILogger::EXCEPTION);
                ++$errors;
            }
            $fileRepository->remove($file);
        }

        $output->writeln(sprintf(
            "Removed %d file records, %d actual files deleted from the storage.",
            count($files),
            $deleted
        ));

        if ($missing) {
            $output->writeln("Total $missing files were missing (only DB records have been deleted).");
        }
        if ($errors) {
            $output->writeln("Total $errors errors encountered and duly logged.");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new DateTime();
        $threshold = $this->uploadsConfig->getRemovalThreshold();

        $output->writeln("Removing unfinished partial uploaded files and their entities...");
        $unfinished = $this->uploadedPartialFiles->findUnfinished($now, $threshold);
        $this->removeOldFiles($unfinished, $this->uploadedPartialFiles, $output, function ($file) {
            $deleted = $this->fileStorage->deleteUploadedPartialFileChunks($file);
            if ($deleted !== $file->getChunks()) {
                $this->logger->log(sprintf(
                    "Uploaded partial file '%s' (%s) had incomplete files. Only %d chunks of %d were actually deleted.",
                    $name = $file->getName(),
                    $id = $file->getId(),
                    $deleted,
                    $file->getChunks()
                ), ILogger::WARNING);
                return false;
            }
            return true;
        });

        $output->writeln("Removing unused uploaded files and their entities...");
        $unused = $this->uploadedFiles->findUnused($now, $threshold);
        $this->removeOldFiles($unused, $this->uploadedFiles, $output, function ($file) {
            if (!$this->fileStorage->deleteUploadedFile($file)) {
                $id = $file->getId();
                $name = $file->getName();
                $this->logger->log("Uploaded file '$name' ($id) has been already deleted.", ILogger::WARNING);
                return false;
            }
            return true;
        });

        $output->writeln("Removing abandoned partial uploaded files (with no corresponding entities)...");
        $partialIds = $this->uploadedPartialFiles->getAllIds();
        $deleted = $this->fileStorage->partialFileChunksCleanup($partialIds);
        $output->writeln("Total $deleted files deleted.");

        return 0;
    }
}
