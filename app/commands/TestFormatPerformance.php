<?php

namespace App\Console;

use App\Exceptions\InternalServerException;
use App\Helpers\Mocks\MockHelper;
use App\V1Module\Presenters\RegistrationPresenter;
use Nette\Application\Request;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestFormatPerformance extends Command
{
    protected static $defaultName = 'test:performance';

    private static $warmupIterations = 100_000;
    private static $measureIterations = 1_000_000;
    private $tests = [];

    public function __construct()
    {
        parent::__construct();

        $this->tests = [
            "loose" => new Request(
                "name",
                method: "POST",
                params: ["action" => "testLoose", "a" => "1", "b" => "a@a.a"],
                post: ["c" => 1.1]
            ),
        ];
    }


    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Generate an OpenAPI documentation from the temporary file created by the swagger:annotate command.'
            . ' The temporary file is deleted afterwards.'
        );
    }

    private function warmup(RegistrationPresenter $presenter, Request $request)
    {
        // test whether the request works
        for ($i = 0; $i < self::$warmupIterations; $i++) {
            $response = $presenter->run($request);
            if ($response->getPayload()["payload"] != "OK") {
                throw new InternalServerException("The endpoint did not run correctly.");
            }
        }
    }

    private function measure(RegistrationPresenter $presenter, Request $request): float
    {
        $startTime = microtime(true);
        for ($i = 0; $i < self::$measureIterations; $i++) {
            $response = $presenter->run($request);
        }
        $endTime = microtime(true);

        return $endTime - $startTime;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("test");

        $presenter = new RegistrationPresenter();
        MockHelper::initPresenter($presenter);

        foreach ($this->tests as $name => $request) {
            $output->writeln("Executing test: " . $name);
            
            $this->warmup($presenter, $request);
            $elapsed = $this->measure($presenter, $request);
    
            $output->writeln("Time elapsed: " . $elapsed);
        }

        return Command::SUCCESS;
    }
}
