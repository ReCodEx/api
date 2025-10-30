<?php

namespace App\Console;

use App\Helpers\MetaFormats\AnnotationConversion\AnnotationToAttributeConverter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scans all Presenters in V1Module and creates a copy of the containing 'presenters' folder, in which all endpoint
 * parameter annotations are converted into attributes.
 * The new folder is named 'presenters2'.
 */
#[AsCommand(name: 'meta:convert', description: 'Convert endpoint parameter annotations to attributes.')]
class MetaConverter extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->generatePresenters();
        return Command::SUCCESS;
    }

    public function generatePresenters()
    {
        $inDir = __DIR__ . "/../V1Module/presenters";
        $outDir = __DIR__ . "/../V1Module/presenters2";

        // create output folder
        if (!is_dir($outDir)) {
            mkdir($outDir);

            // copy base subfolder
            $inBaseDir = $inDir . "/base";
            $outBaseDir = $outDir . "/base";
            mkdir($outBaseDir);
            $baseFilenames = scandir($inBaseDir);
            foreach ($baseFilenames as $filename) {
                if (!str_ends_with($filename, ".php")) {
                    continue;
                }

                copy($inBaseDir . "/" . $filename, $outBaseDir . "/" . $filename);
            }
        }

        // copy and convert Presenters
        $filenames = scandir($inDir);
        foreach ($filenames as $filename) {
            if (!str_ends_with($filename, "Presenter.php")) {
                continue;
            }

            $filepath = $inDir . "/" . $filename;
            $newContent = AnnotationToAttributeConverter::convertFile($filepath);
            $newFile = fopen($outDir . "/" . $filename, "w");
            fwrite($newFile, $newContent);
            fclose($newFile);
        }
    }
}
