<?php
namespace App\Console;

use App\Helpers\UploadsConfig;
use App\Model\Repository\UploadedFiles;
use DateTime;
use Nette\IOException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Nette\Utils\FileSystem;
use Tracy\ILogger;

class CleanupUploads extends Command {
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

  public function __construct(UploadsConfig $config, UploadedFiles $uploadedFiles, ILogger $logger) {
    parent::__construct();
    $this->uploadsConfig = $config;
    $this->uploadedFiles = $uploadedFiles;
    $this->logger = $logger;
  }

  protected function configure() {
    $this->setName('db:cleanup:uploads')->setDescription('Remove unused uploaded files.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $now = new DateTime();
    $unused = $this->uploadedFiles->findUnused($now, $this->uploadsConfig->getRemovalThreshold());

    foreach ($unused as $file) {
      $this->uploadedFiles->remove($file);
      if (!$file->isLocal()) {
        continue;
      }

      try {
        FileSystem::delete($file->getLocalFilePath());
      } catch (IOException $e) {
        $this->logger->log($e->getMessage(), ILogger::EXCEPTION);
      }
    }

    $output->writeln(sprintf("Removed %d unused files", count($unused)));
    return 0;
  }
}
