<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Storage;
use Nette\Utils\Strings;
use Symfony\Component\Yaml\Yaml;

class TestJobConfigStorage extends Tester\TestCase
{
    private $jobConfigFileName;

    public function setUp() {
        $tmpName = tempnam(__DIR__ . '/../../temp/tests', 'bla');
        $name = "$tmpName.yml";
        rename($tmpName, $name);
        $this->jobConfigFileName = $name;
        file_put_contents($this->jobConfigFileName, "bla"); // @todo prepare real config
    }

    public function tearDown() {
        @unlink($this->jobConfigFileName); // the file might not already exist
    }

    public function testArchivation() {
        $oldContents = file_get_contents($this->jobConfigFileName);
        $newFilePath = Storage::archiveJobConfig($this->jobConfigFileName, "my_custom_prefix_");
        $newContents = file_get_contents($newFilePath);
        $newFileName = pathinfo($newFilePath, PATHINFO_FILENAME);
        Assert::true(Strings::startsWith($newFileName, "my_custom_prefix_"));
        Assert::equal($oldContents, $newContents);
    }

    public function testArchivationMultipleTimes() {
        // first make sure the file is real
        Assert::true(is_file($this->jobConfigFileName));

        $firstArchivedFilePath = Storage::archiveJobConfig($this->jobConfigFileName, "my_custom_prefix_");
        file_put_contents($this->jobConfigFileName, "bla"); // the file was moved => create new with arbitrary cotnents..
        $secondArchivedFilePath = Storage::archiveJobConfig($this->jobConfigFileName, "my_custom_prefix_");

        // both archives must exist
        Assert::true(is_file($firstArchivedFilePath));
        Assert::true(is_file($secondArchivedFilePath));

        // test the suffixes
        Assert::true(Strings::endsWith($firstArchivedFilePath, "_1.yml"));
        Assert::true(Strings::endsWith($secondArchivedFilePath, "_2.yml"));

        // cleanup
        unlink($firstArchivedFilePath);
        unlink($secondArchivedFilePath);
    }

}

# Testing methods run
$testCase = new TestJobConfigStorage;
$testCase->run();
