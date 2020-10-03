<?php

namespace App\Console;

use App\Helpers\WorkerFilesConfig;
use App\Helpers\FileStorageManager;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupWorkerTmpFiles extends Command
{
    /**
     * @var FileStorageManager
     */
    private $fileStorage;

    /**
     * @var WorkerFilesConfig
     */
    private $workerFilesConfig;

    public function __construct(FileStorageManager $fileStorage, WorkerFilesConfig $config)
    {
        parent::__construct();
        $this->fileStorage = $fileStorage;
        $this->workerFilesConfig = $config;
    }

    protected function configure()
    {
        $this->setName('fs:cleanup:worker')
            ->setDescription('Remove old worker files (tmp solution archives and lingering results archives).');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $thresholdDate = new DateTime();
        $thresholdDate->modify("-" . $this->workerFilesConfig->getRemovalThreshold());
        $deleted = $this->fileStorage->workerFilesCleanup($thresholdDate);
        $output->writeln("Removed $deleted old files");
        return 0;
    }
}
