<?php

namespace App\Console;

use App\Helpers\Mocks\MockHelper;
use App\V1Module\Presenters\RegistrationPresenter;
use Nette\Application\Request;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenApi\Generator;

/**
 * Command that consumes a temporary file containing endpoint annotations and generates a swagger documentation.
 */
class TestFormatPerformance extends Command
{
    protected static $defaultName = 'meta:performance';

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Generate an OpenAPI documentation from the temporary file created by the swagger:annotate command.'
            . ' The temporary file is deleted afterwards.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("test");

        $presenter = new RegistrationPresenter();
        MockHelper::initPresenter($presenter);

        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "test", "a" => "497dcba3-ecbf-4587-a2dd-5eb0665e6880", "b" => "1"],
            post: ["c" => 1.1]
        );

        // test whether the request works        
        $response = $presenter->run($request);
        if ($response->getPayload()["payload"] != "OK") {
            $output->writeln("The endpoint did not run correctly.");
            return Command::FAILURE;
        }
        
        $startTime = microtime(true);
        $iterations = 100_000;
        for ($i = 0; $i < $iterations; $i++) {
            $response = $presenter->run($request);
        }
        $endTime = microtime(true);
        
        $elapsed = $endTime - $startTime;
        $output->writeln($elapsed);

        return Command::SUCCESS;
    }
}
