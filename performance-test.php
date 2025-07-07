<?php

use Nette\Application\Request;
use App\Exceptions\InternalServerException;
use App\Helpers\Mocks\MockHelper;
use App\V1Module\Presenters\RegistrationPresenter;
use Tester\Assert;

require __DIR__ . '/tests/bootstrap.php';

class PerformanceTest
{
    protected static $defaultName = 'test:performance';

    private static $warmupIterations = 100_000;
    private static $measureIterations = 1_000_000;
    private $tests = [];
    public $output = [];

    public function __construct()
    {
        $this->tests = [
            "loose" => new Request(
                "name",
                method: "POST",
                params: ["action" => "testLoose", "a" => "1", "b" => "a@a.a"],
                post: ["c" => 1.1]
            ),
        ];
    }

    public function printResults()
    {
        foreach ($this->output as $line) {
            echo $line . "\n";
        }
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

    public function execute()
    {
        $this->output[] = "test";
        // echo "test\n";

        $presenter = new RegistrationPresenter();
        MockHelper::initPresenter($presenter);

        foreach ($this->tests as $name => $request) {
            $this->output[] = "Executing test: " . $name;
            
            $this->warmup($presenter, $request);
            $elapsed = $this->measure($presenter, $request);
    
            $this->output[] = "Time elapsed: " . $elapsed;
        }
    }
}

$test = new PerformanceTest();
$test->execute();
$test->printResults();
Assert::true(true);
