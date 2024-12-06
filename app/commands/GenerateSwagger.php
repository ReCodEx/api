<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \OpenApi\Generator;

class GenerateSwagger extends Command
{
    protected static $defaultName = 'swagger:generate';

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Generate an OpenAPI documentation from the temporary file created by the swagger:annotate command.'
            . ' The temporary file is deleted afterwards.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = __DIR__ . '/../V1Module/presenters/_autogenerated_annotations_temp.php';

        // check if file exists
        if (!file_exists($path)) {
            $output->writeln("Error in GenerateSwagger: Temp annotation file not found.");
            return Command::FAILURE;
        }

        $openapi = Generator::scan([$path]);

        $output->writeln($openapi->toYaml());

        // delete the temp file
        unlink($path);

        return Command::SUCCESS;
    }
}
