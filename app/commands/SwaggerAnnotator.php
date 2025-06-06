<?php

namespace App\Console;

use App\Helpers\Swagger\TempAnnotationFileBuilder;
use App\Helpers\Swagger\AnnotationHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * Command that creates a temporary file for swagger documentation generation.
 * The command uses the RouterFactory to find all endpoints.
 * The temporary file is consumed by the swagger:generate command.
 */
class SwaggerAnnotator extends Command
{
    protected static $defaultName = 'swagger:annotate';
    private static $autogeneratedAnnotationFilePath = 'app/V1Module/presenters/_autogenerated_annotations_temp.php';

    protected function configure(): void
    {
        $filePath = self::$autogeneratedAnnotationFilePath;
        $this->setName(self::$defaultName)->setDescription(
            "Extracts endpoint method annotations and puts them into a temporary file that can be used to generate"
                . " an OpenAPI documentation. The file is located at {$filePath}"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // create a temporary file containing transpiled annotations usable by the external library (Swagger-PHP)
            $fileBuilder = new TempAnnotationFileBuilder(self::$autogeneratedAnnotationFilePath);
            $fileBuilder->startClass('__Autogenerated_Annotation_Controller__', '1.0', 'ReCodEx API');

            // get all routes of the api
            $routesMetadata = AnnotationHelper::getRoutesMetadata();
            foreach ($routesMetadata as $route) {
                // extract data from the existing annotations
                $annotationData = AnnotationHelper::extractAttributeData(
                    $route["class"],
                    $route['method'],
                );

                // add an empty method to the file with the transpiled annotations
                $fileBuilder->addAnnotatedMethod(
                    $route['method'],
                    $annotationData->toSwaggerAnnotations($route["route"])
                );
            }
            $fileBuilder->endClass();

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln("Error in SwaggerAnnotator: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
