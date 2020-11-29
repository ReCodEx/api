<?php

namespace App\Console;

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Repository\SupplementaryExerciseFiles;
use App\Model\Repository\AttachmentFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\ILogger;

class CleanupExercisesFiles extends Command
{
    /**
     * @var SupplementaryExerciseFiles
     */
    private $supplementaryFiles;

    /**
     * @var AttachmentFiles
     */
    private $attachmentFiles;

    /**
     * @var ILogger
     */
    private $logger;

    /**
     * @var FileStorageManager
     */
    private $fileStorage;


    public function __construct(
        SupplementaryExerciseFiles $supplementaryFiles,
        AttachmentFiles $attachmentFiles,
        ILogger $logger,
        FileStorageManager $fileStorage
    ) {
        parent::__construct();
        $this->supplementaryFiles = $supplementaryFiles;
        $this->attachmentFiles = $attachmentFiles;
        $this->logger = $logger;
        $this->fileStorage = $fileStorage;
    }

    protected function configure()
    {
        $this->setName('db:cleanup:exercise-files')
            ->setDescription('Remove unused supplementary and attachment files (only DB records are removed in case of supplementary files).');
    }

    private function removeUnusedSupplementaryFiles(OutputInterface $output)
    {
        $unused = $this->supplementaryFiles->findUnused();
        foreach ($unused as $file) {
            $this->supplementaryFiles->remove($file);
        }

        $output->writeln(sprintf("Removed %d unused supplementary file records.", count($unused)));
    }

    private function removeUnusedAttachmentFiles(OutputInterface $output)
    {
        $unused = $this->attachmentFiles->findUnused();
        $deleted = 0;
        $missing = 0;
        $errors = 0;

        foreach ($unused as $file) {
            try {
                if (!$this->fileStorage->deleteAttachmentFile($file)) {
                    $id = $file->getId();
                    $name = $file->getName();
                    $this->logger->log("Attachment file '$name' ($id) has been already deleted.", ILogger::WARNING);
                    ++$missing;
                } else {
                    ++$deleted;
                }
            } catch (FileStorageException $e) {
                $this->logger->log($e->getMessage(), ILogger::EXCEPTION);
                ++$errors;
            }
            $this->attachmentFiles->remove($file);
        }

        $output->writeln(sprintf(
            "Removed %d unused attachment file records, %d actual files deleted from the storage.",
            count($unused),
            $deleted
        ));
        if ($missing) {
            $output->writeln("Total $missing files were missing (only DB records have been deleted).");
        }
        if ($errors) {
            $output->writeln("Total $errors errors encountered and duly logged.");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->removeUnusedSupplementaryFiles($output);
        $this->removeUnusedAttachmentFiles($output);
        return 0;
    }
}
