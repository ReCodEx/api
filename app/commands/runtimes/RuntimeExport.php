<?php

namespace App\Console;

use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\Pipelines;
use App\Model\Entity\Pipeline;
use App\Helpers\FileStorageManager;
use ZipArchive;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class RuntimeExport extends Command
{
    protected static $defaultName = 'runtimes:export';

    /** @var RuntimeEnvironments */
    private $runtimeEnvironments;

    /** @var Pipelines */
    private $pipelines;

    /** @var FileStorageManager */
    private $fileManager;


    public function __construct(
        RuntimeEnvironments $runtimeEnvironments,
        Pipelines $pipelines,
        FileStorageManager $fileManager,
    ) {
        parent::__construct();
        $this->runtimeEnvironments = $runtimeEnvironments;
        $this->pipelines = $pipelines;
        $this->fileManager = $fileManager;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Export runtime environment and its pipelines into a ZIP package.'
        )->addArgument('runtime', InputArgument::REQUIRED, 'ID of the runtime environment to be exported.')
            ->addArgument('saveAs', InputArgument::REQUIRED, 'Path to the output ZIP archive.');
    }

    protected static function preprocessPipeline(Pipeline $pipeline)
    {
        $supplementaryFiles = [];
        foreach ($pipeline->getSupplementaryEvaluationFiles()->getValues() as $file) {
            $supplementaryFiles[] = [
                "name" => $file->getName(),
                "uploadedAt" => $file->getUploadedAt()->getTimestamp(),
                "size" => $file->getFileSize(),
                "hash" => $file->getHashName(),
            ];
        }

        return [
            "id" => $pipeline->getId(),
            "name" => $pipeline->getName(),
            "version" => $pipeline->getVersion(),
            "createdAt" => $pipeline->getCreatedAt()->getTimestamp(),
            "updatedAt" => $pipeline->getUpdatedAt()->getTimestamp(),
            "description" => $pipeline->getDescription(),
            "supplementaryFiles" => $supplementaryFiles,
            "parameters" => array_merge(Pipeline::DEFAULT_PARAMETERS, $pipeline->getParameters()->toArray()),
        ];
    }

    /**
     * Helper function that creates a new entry in ZIP archive and fill it as JSON file.
     */
    protected static function addJsonFile(ZipArchive $zip, string $entry, $json)
    {
        if (!$zip->addFromString($entry, json_encode($json, JSON_PRETTY_PRINT))) {
            throw new RuntimeException("Writing to the ZIP archive failed when JSON entry '$entry' was created.");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            $fileName = $input->getArgument('saveAs');
            $runtimeId = $input->getArgument('runtime');
            $runtime = $this->runtimeEnvironments->findOrThrow($runtimeId);

            // Open the ZIP archive and start writing.
            $zip = new ZipArchive();
            $opened = $zip->open($fileName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($opened !== true) {
                throw new RuntimeException("Unable to open file '$fileName' for writing (code $opened).");
            }
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }

        try {
            // Prepare the main JSON file called manifest (with all DB data except pipeline configs).
            $manifest = [
                'pkgVersion' => 1,
                'runtime' => $runtime,
                'pipelines' => [],
            ];

            $pipelines = $this->pipelines->getRuntimeEnvironmentPipelines($runtimeId);
            foreach ($pipelines as $pipeline) {
                $manifest['pipelines'][] = self::preprocessPipeline($pipeline);
            }

            self::addJsonFile($zip, "manifest.json", $manifest);

            // Add all pipeline config files as separate JSONs.
            foreach ($pipelines as $pipeline) {
                $config = $pipeline->getPipelineConfig()->getParsedPipeline();
                self::addJsonFile($zip, $pipeline->getId() . ".json", $config);
            }

            // Add supplementary pipeline files
            foreach ($pipelines as $pipeline) {
                $files = $pipeline->getSupplementaryEvaluationFiles()->getValues();
                foreach ($files as $supFile) {
                    $name = $supFile->getName();
                    $pid = $pipeline->getId();
                    $immFile = $supFile->getFile($this->fileManager);
                    if (!$immFile) {
                        throw new RuntimeException(
                            "Supplementary file '$name' of pipeline '$pid' is not present in file storage."
                        );
                    }

                    $immFile->addToZip($zip, "$pid/$name");
                }
            }
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr->writeln("Error: $msg");

            // try to erase our mistakes
            @$zip->close();
            @unlink($fileName);
            return Command::FAILURE;
        }

        if (!$zip->close()) {
            $stderr->writeln("Unable to finalize and close ZIP file '$fileName'.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
