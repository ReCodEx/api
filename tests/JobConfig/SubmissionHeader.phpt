<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\SubmissionHeader;
use App\Helpers\JobConfig\Loader;


class TestSubmissionHeader extends Tester\TestCase
{
    static $minimalConfig = [
        "job-id" => "id123",
        "file-collector" => "https://collector",
        "hw-groups" => ["A", "B"]
    ];

    /** @var Loader */
    private $builder;

    public function __construct()
    {
        $this->builder = new Loader();
    }

    public function testValidConstructionRequired()
    {
        $header = $this->builder->loadSubmissionHeader(self::$minimalConfig);
        Assert::equal("id123", $header->getId());
        Assert::equal("student", $header->getType());
        Assert::equal("student_id123", $header->getJobId());
        Assert::false($header->getLog());
    }

    public function testValidConstructionAdditional()
    {
        $config = self::$minimalConfig;
        $config["somekey"] = "somevalue";
        $config["otherkey"] = "othervalue";
        $header = $this->builder->loadSubmissionHeader($config);
        Assert::equal(array("somekey" => "somevalue", "otherkey" => "othervalue"), $header->getAdditionalData());
    }

    public function testInvalidJobId()
    {
        $config = self::$minimalConfig;
        $config["job-id"] = "wrtype_id";
        Assert::exception(
            function () use ($config) {
                $this->builder->loadSubmissionHeader($config);
            },
            JobConfigLoadingException::CLASS
        );
    }

    public function testInvalidHardwareGroups()
    {
        $config = self::$minimalConfig;
        $config["hw-groups"] = "bla bla";
        Assert::exception(
            function () use ($config) {
                $this->builder->loadSubmissionHeader($config);
            },
            JobConfigLoadingException::CLASS
        );
    }

    public function testMissingHardwareGroups()
    {
        $config = self::$minimalConfig;
        unset($config["hw-groups"]);
        Assert::exception(
            function () use ($config) {
                $this->builder->loadSubmissionHeader($config);
            },
            JobConfigLoadingException::CLASS
        );
    }

    public function testAddingHardwareGroups()
    {
        $header = $this->builder->loadSubmissionHeader(self::$minimalConfig);

        $header->addHardwareGroup("C");
        Assert::equal(["A", "B", "C"], $header->getHardwareGroups());

        $header->addHardwareGroup("B");
        Assert::equal(["A", "B", "C"], $header->getHardwareGroups());
    }

    public function testRemovingHardwareGroups()
    {
        $header = $this->builder->loadSubmissionHeader(self::$minimalConfig);

        $header->removeHardwareGroup("B");
        Assert::equal(["A"], $header->getHardwareGroups());

        $header->removeHardwareGroup("C");
        Assert::equal(["A"], $header->getHardwareGroups());
    }

    public function testSetJobId()
    {
        $header = $this->builder->loadSubmissionHeader(self::$minimalConfig);
        Assert::equal("id123", $header->getId());
        $header->setId("mojeid");
        Assert::equal("mojeid", $header->getId());

        $header->setType("reference");
        Assert::equal("reference", $header->getType());

        Assert::equal("reference_mojeid", $header->getJobId());
        Assert::exception(
            function () use ($header) {
                $header->setType("unknown");
            },
            JobConfigLoadingException::CLASS
        );
    }

    public function testSetFileCollector()
    {
        $header = $this->builder->loadSubmissionHeader(self::$minimalConfig);
        Assert::equal("https://collector", $header->getFileCollector());
        $header->setFileCollector("https://new.collector");
        Assert::equal("https://new.collector", $header->getFileCollector());
    }

    public function testSetHardwareGroups()
    {
        $header = $this->builder->loadSubmissionHeader(self::$minimalConfig);
        Assert::equal(["A", "B"], $header->getHardwareGroups());
        $header->setHardwareGroups(["A", "C"]);
        Assert::equal(["A", "C"], $header->getHardwareGroups());
    }

    public function testSetLog()
    {
        $header = $this->builder->loadSubmissionHeader(self::$minimalConfig);
        Assert::false($header->getLog());
        $header->setLog(true);
        Assert::true($header->getLog());
    }

    public function testToArray()
    {
        $config = self::$minimalConfig;
        $config["somekey"] = "somevalue";
        $config["otherkey"] = "othervalue";
        $header = $this->builder->loadSubmissionHeader($config);

        $expected = [
            "job-id" => "student_id123",
            "file-collector" => "https://collector",
            "log" => "false",
            "hw-groups" => ["A", "B"],
            "somekey" => "somevalue",
            "otherkey" => "othervalue"
        ];
        Assert::equal($expected, $header->toArray());
    }
}

# Testing methods run
$testCase = new TestSubmissionHeader();
$testCase->run();
