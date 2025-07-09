<?php

use Nette\Application\Request;
use App\Exceptions\InternalServerException;
use App\V1Module\Presenters\RegistrationPresenter;
use Tester\Assert;

require_once __DIR__ . "/tests/Mocks/MockHelper.php";
require __DIR__ . '/tests/bootstrap.php';

class PerformanceTest
{
    protected static $defaultName = 'test:performance';

    private static $warmupIterations = 1000;
    private static $measureIterations = 10000;
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
            "format" => new Request(
                "name",
                method: "POST",
                params: ["action" => "testFormat", "a" => "1", "b" => "a@a.a"],
                post: ["c" => 1.1]
            ),
            "5UrlLoose" => new Request(
                "name",
                method: "POST",
                params: ["action" => "test5UrlLoose", "a" => "a", "b" => "b", "c" => "c", "d" => "d", "e" => "e"],
            ),
            "5UrlFormat" => new Request(
                "name",
                method: "POST",
                params: ["action" => "test5UrlFormat", "a" => "a", "b" => "b", "c" => "c", "d" => "d", "e" => "e"],
            ),
            "5BodyLoose" => new Request(
                "name",
                method: "POST",
                params: ["action" => "test5BodyLoose"],
                post: ["a" => "a", "b" => "b", "c" => "c", "d" => "d", "e" => "e"]
            ),
            "5BodyFormat" => new Request(
                "name",
                method: "POST",
                params: ["action" => "test5BodyFormat"],
                post: ["a" => "a", "b" => "b", "c" => "c", "d" => "d", "e" => "e"]
            ),
            "5BodyFormatNested" => new Request(
                "name",
                method: "POST",
                params: ["action" => "test5BodyFormatNested"],
                post: ["a" => "a", "nested" => [
                    "b" => "b", "nested" => [
                        "c" => "c", "nested" => [
                            "d" => "d", "nested" => [
                                "e" => "e"
                            ]
                        ]
                    ]
                ]]
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
