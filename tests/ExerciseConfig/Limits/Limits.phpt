<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\Yaml;

/**
 * @testCase
 */
class TestLimits extends Tester\TestCase
{
    static $sample = [
        "memory" => 123,
        "wall-time" => 456.7
    ];
    static $cfg = [
        ["memory" => 123, "cpu-time" => 456.7],
        ["memory" => 321, "wall-time" => 645.1],
        ["memory" => 123]
    ];
    static $optional = [
        "wall-time" => 2.2,
        "cpu-time" => 3.3,
        "memory" => 5,
        "parallel" => 6
    ];

    /** @var Loader */
    private $loader;

    public function __construct()
    {
        $this->loader = new Loader(new BoxService());
    }

    public function testSerialization()
    {
        $deserialized = Yaml::parse((string)$this->loader->loadLimits(self::$sample));
        ksort($deserialized);
        Assert::equal(self::$sample, $deserialized);
    }

    public function testParsingA()
    {
        $limits = $this->loader->loadLimits(self::$cfg[0]);
        Assert::equal(123, $limits->getMemoryLimit());
        Assert::type("int", $limits->getMemoryLimit());
        Assert::equal(456.7, $limits->getCpuTime());
        Assert::type("float", $limits->getCpuTime());
    }

    public function testParsingB()
    {
        $limits = $this->loader->loadLimits(self::$cfg[1]);
        Assert::equal(321, $limits->getMemoryLimit());
        Assert::type("int", $limits->getMemoryLimit());
        Assert::equal(645.1, $limits->getWallTime());
        Assert::type("float", $limits->getWallTime());
    }

    public function testNoTimeLimits()
    {
        Assert::exception(
            function () {
                $this->loader->loadLimits(self::$cfg[2]);
            },
            ExerciseConfigException::class
        );
    }

    public function testOptional()
    {
        $limits = $this->loader->loadLimits(self::$optional);
        Assert::equal(2.2, $limits->getWallTime());
        Assert::equal(3.3, $limits->getCpuTime());
        Assert::equal(5, $limits->getMemoryLimit());
        Assert::equal(6, $limits->getParallel());
        Assert::equal(self::$optional, $limits->toArray());
    }
}

# Testing methods run
$testCase = new TestLimits();
$testCase->run();
