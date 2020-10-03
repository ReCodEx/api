<?php

namespace App\Console;

use App\Helpers\UploadsConfig;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\FileStorageException;
use App\Model\Repository\UploadedFiles;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\ILogger;

class CleanupUploads extends Command
{
    /**
     * @var UploadsConfig
     */
    private $uploadsConfig;

    /**
     * @var UploadedFiles
     */
    private $uploadedFiles;

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
        ILogger $logger,
        FileStorageManager $fileStorage
    ) {
        parent::__construct();
        $this->uploadsConfig = $config;
        $this->uploadedFiles = $uploadedFiles;
        $this->logger = $logger;
        $this->fileStorage = $fileStorage;
    }

    protected function configure()
    {
        $this->setName('db:cleanup:uploads')->setDescription('Remove unused uploaded files and corresponding DB records.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new DateTime();
        $unused = $this->uploadedFiles->findUnused($now, $this->uploadsConfig->getRemovalThreshold());

        foreach ($unused as $file) {
            try {
                if (!$this->fileStorage->deleteUploadedFile($file)) {
                    $id = $file->getId();
                    $name = $file->getName();
                    $this->logger->log("Uploaded file '$name' ($id) has been already deleted.", ILogger::WARNING);
                }
            } catch (FileStorageException $e) {
                $this->logger->log($e->getMessage(), ILogger::EXCEPTION);
            }
            $this->uploadedFiles->remove($file);
        }

        $output->writeln(sprintf("Removed %d unused files", count($unused)));
        return 0;
    }
}
