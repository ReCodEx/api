<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Loader;
use App\Exceptions\MalformedJobConfigException;
use App\Helpers\JobConfig\Storage;
use Nette\Utils\Strings;

class TestJobConfigStorage extends Tester\TestCase
{
  private $jobConfigFileName;

  /** @var Storage */
  private $storage;

  public function setUp() {
    $filePath = sys_get_temp_dir() . '/test-job-config-loader.yml';
    file_put_contents($filePath, self::$jobConfig);
    $this->jobConfigFileName = $filePath;
    $this->storage = new Storage(sys_get_temp_dir());
  }

  public function tearDown() {
    @unlink($this->jobConfigFileName); // the file might not already exist
  }

  public function testArchiving() {
    $oldContents = file_get_contents($this->jobConfigFileName);
    $newFilePath = $this->storage->archive($this->jobConfigFileName, "my_custom_prefix_");
    $newContents = file_get_contents($newFilePath);
    $newFileName = pathinfo($newFilePath, PATHINFO_FILENAME);
    Assert::true(Strings::startsWith($newFileName, "my_custom_prefix_"));
    Assert::equal($oldContents, $newContents);

    // cleanup
    unlink($newFilePath);
  }

  public function testArchivingMultipleTimes() {
    // first make sure the file is real
    Assert::true(is_file($this->jobConfigFileName));

    $firstArchivedFilePath = $this->storage->archive($this->jobConfigFileName, "my_custom_prefix_");
    file_put_contents($this->jobConfigFileName, self::$jobConfig);
    $secondArchivedFilePath = $this->storage->archive($this->jobConfigFileName, "my_custom_prefix_");

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

  public function testCanBeLoaded() {
    $jobConfig = $this->storage->parse(self::$jobConfig);
    Assert::type(JobConfig::CLASS, $jobConfig);
    Assert::equal("hippoes", $jobConfig->getSubmissionHeader()->getId());
  }

  public function testRejectInvalidYaml() {
    Assert::exception(function() {
        $invalidCfg = "bla bla:
            \t- ratatat:
            - foo
            - bar";
        $this->storage->parse($invalidCfg);
    }, MalformedJobConfigException::CLASS);
  }

  public function testLoadFromFile() {
    $jobConfig = $this->storage->get($this->jobConfigFileName);
    Assert::equal("hippoes", $jobConfig->getSubmissionHeader()->getId());
    Assert::type(JobConfig::CLASS, $jobConfig);
  }

  public function testCorrectInterpretation() {
    $jobConfig = $this->storage->parse(self::$jobConfig);
    Assert::equal(31, $jobConfig->getTasksCount());
    Assert::equal(31, count($jobConfig->getTasks()));
    Assert::equal(6, count($jobConfig->getTests()));
  }

  static $jobConfig = <<<'EOS'
# Hippoes config file
# prerequisites: judge binary in /usr/bin/recodex-judge-normal
#                reachable input and output files - in /tmp/tmpxxxxxx/tasks for our testing file_server.py
# expected result: results.zip uploaded to file server with result file and job log file
#
---  # only one document which contains job, aka. list of tasks and some general infos
submission:  # happy hippoes fence
    job-id: hippoes
    language: c
    file-collector: http://localhost:9999/tasks
    log: true
    hw-groups:
        - A
        - B
tasks:
    - task-id: "compilation"
      type: initiation
      priority: 2
      fatal-failure: true
      cmd:
          bin: "/usr/bin/gcc"
          args:
              - "solution.c"
              - "-o"
              - "a.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                parallel: 0
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
      checkScore: true
    - task-id: "fetch_test_1"
      priority: 4
      fatal-failure: false
      dependencies:
          - compilation
      cmd:
          bin: "fetch"
          args:
              - "b5cc16b8ca08dc099b50768999202ae3807f60c6"
              - "${SOURCE_DIR}/kuly.in"
    - task-id: "evaluation_test_1"
      test-id: A
      type: execution
      priority: 5
      fatal-failure: false
      dependencies:
          - fetch_test_1
      cmd:
          bin: "a.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                time: 0.5
                memory: 8192
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
      has-stats: true
    - task-id: "fetch_test_solution_1"
      priority: 6
      fatal-failure: false
      dependencies:
          - evaluation_test_1
      cmd:
          bin: "fetch"
          args:
              - "63f6aecf2244ec0a9cff2923fa2290cea57c093c"
              - "${SOURCE_DIR}/1.out"
    - task-id: "judging_test_1"
      test-id: A
      type: evaluation
      priority: 7
      fatal-failure: false
      dependencies:
          - fetch_test_solution_1
      cmd:
          bin: "${JUDGES_DIR}/recodex-judge-normal"
          args:
              - "1.out"
              - "plot.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                parallel: 0
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "rm_junk_test_1"
      priority: 8
      fatal-failure: false
      dependencies:
          - judging_test_1
      cmd:
          bin: "rm"
          args:
              - "${SOURCE_DIR}/kuly.in"
              - "${SOURCE_DIR}/plot.out"
              - "${SOURCE_DIR}/1.out"
    - task-id: "fetch_test_2"
      priority: 4
      fatal-failure: false
      dependencies:
          - compilation
      cmd:
          bin: "fetch"
          args:
              - "2ac6ba84d1c016019bed328abe1fac06c3efbe6d"
              - "${SOURCE_DIR}/kuly.in"
    - task-id: "evaluation_test_2"
      priority: 5
      test-id: B
      type: execution
      fatal-failure: false
      dependencies:
          - fetch_test_2
      cmd:
          bin: "a.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                time: 0.5
                memory: 8192
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "fetch_test_solution_2"
      priority: 6
      fatal-failure: false
      dependencies:
          - evaluation_test_2
      cmd:
          bin: "fetch"
          args:
              - "fca97a3ab1c9c4624726380451d7446b454da62c"
              - "${SOURCE_DIR}/2.out"
    - task-id: "judging_test_2"
      test-id: B
      type: evaluation
      priority: 7
      fatal-failure: false
      dependencies:
          - fetch_test_solution_2
      cmd:
          bin: "${JUDGES_DIR}/recodex-judge-normal"
          args:
              - "2.out"
              - "plot.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                parallel: 0
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "rm_junk_test_2"
      priority: 8
      fatal-failure: false
      dependencies:
          - judging_test_2
      cmd:
          bin: "rm"
          args:
              - "${SOURCE_DIR}/kuly.in"
              - "${SOURCE_DIR}/plot.out"
              - "${SOURCE_DIR}/2.out"
    - task-id: "fetch_test_3"
      priority: 4
      fatal-failure: false
      dependencies:
          - compilation
      cmd:
          bin: "fetch"
          args:
              - "f1666f92686d843f93f6f66ca1081016d404af49"
              - "${SOURCE_DIR}/kuly.in"
    - task-id: "evaluation_test_3"
      test-id: C
      type: execution
      priority: 5
      fatal-failure: false
      dependencies:
          - fetch_test_3
      cmd:
          bin: "a.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                time: 0.5
                memory: 8192
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "fetch_test_solution_3"
      priority: 6
      fatal-failure: false
      dependencies:
          - evaluation_test_3
      cmd:
          bin: "fetch"
          args:
              - "4f1cb664089df4f9eced4436d62fec1ad2fe8ece"
              - "${SOURCE_DIR}/3.out"
    - task-id: "judging_test_3"
      test-id: C
      type: evaluation
      priority: 7
      fatal-failure: false
      dependencies:
          - fetch_test_solution_3
      cmd:
          bin: "${JUDGES_DIR}/recodex-judge-normal"
          args:
              - "3.out"
              - "plot.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                parallel: 0
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "rm_junk_test_3"
      priority: 8
      fatal-failure: false
      dependencies:
          - judging_test_3
      cmd:
          bin: "rm"
          args:
              - "${SOURCE_DIR}/kuly.in"
              - "${SOURCE_DIR}/plot.out"
              - "${SOURCE_DIR}/3.out"
    - task-id: "fetch_test_4"
      priority: 4
      fatal-failure: false
      dependencies:
          - compilation
      cmd:
          bin: "fetch"
          args:
              - "9c677f28e20a03d429d58186e3b38baba2fddeb5"
              - "${SOURCE_DIR}/kuly.in"
    - task-id: "evaluation_test_4"
      test-id: D
      type: execution
      priority: 5
      fatal-failure: false
      dependencies:
          - fetch_test_4
      cmd:
          bin: "a.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                time: 0.5
                memory: 8192
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "fetch_test_solution_4"
      priority: 6
      fatal-failure: false
      dependencies:
          - evaluation_test_4
      cmd:
          bin: "fetch"
          args:
              - "d34fbdde8baafcd0d0059d577f44cf3936d8d791"
              - "${SOURCE_DIR}/4.out"
    - task-id: "judging_test_4"
      test-id: D
      type: evaluation
      priority: 7
      fatal-failure: false
      dependencies:
          - fetch_test_solution_4
      cmd:
          bin: "${JUDGES_DIR}/recodex-judge-normal"
          args:
              - "4.out"
              - "plot.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                parallel: 0
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "rm_junk_test_4"
      priority: 8
      fatal-failure: false
      dependencies:
          - judging_test_4
      cmd:
          bin: "rm"
          args:
              - "${SOURCE_DIR}/kuly.in"
              - "${SOURCE_DIR}/plot.out"
              - "${SOURCE_DIR}/4.out"
    - task-id: "fetch_test_5"
      priority: 4
      fatal-failure: false
      dependencies:
          - compilation
      cmd:
          bin: "fetch"
          args:
              - "7614357c5e826690abbb74548cc2b2a3b81842bd"
              - "${SOURCE_DIR}/kuly.in"
    - task-id: "evaluation_test_5"
      test-id: E
      type: execution
      priority: 5
      fatal-failure: false
      dependencies:
          - fetch_test_5
      cmd:
          bin: "a.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                time: 0.5
                memory: 8192
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "fetch_test_solution_5"
      priority: 6
      fatal-failure: false
      dependencies:
          - evaluation_test_5
      cmd:
          bin: "fetch"
          args:
              - "aff7a24653242491215af4164756244283658b9e"
              - "${SOURCE_DIR}/5.out"
    - task-id: "judging_test_5"
      test-id: E
      type: evaluation
      priority: 7
      fatal-failure: false
      dependencies:
          - fetch_test_solution_5
      cmd:
          bin: "${JUDGES_DIR}/recodex-judge-normal"
          args:
              - "5.out"
              - "plot.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                parallel: 0
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "rm_junk_test_5"
      priority: 8
      fatal-failure: false
      dependencies:
          - judging_test_5
      cmd:
          bin: "rm"
          args:
              - "${SOURCE_DIR}/kuly.in"
              - "${SOURCE_DIR}/plot.out"
              - "${SOURCE_DIR}/5.out"
    - task-id: "fetch_test_6"
      priority: 4
      fatal-failure: false
      dependencies:
          - compilation
      cmd:
          bin: "fetch"
          args:
              - "4db3be81376f812535b0d241ced5b445ff16f39f"
              - "${SOURCE_DIR}/kuly.in"
    - task-id: "evaluation_test_6"
      test-id: F
      type: execution
      priority: 5
      fatal-failure: false
      dependencies:
          - fetch_test_6
      cmd:
          bin: "a.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                time: 0.75
                memory: 16384
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "fetch_test_solution_6"
      priority: 6
      fatal-failure: false
      dependencies:
          - evaluation_test_6
      cmd:
          bin: "fetch"
          args:
              - "e337043148adfde61ce25a315a31096849a58f42"
              - "${SOURCE_DIR}/6.out"
    - task-id: "judging_test_6"
      test-id: F
      type: evaluation
      priority: 7
      fatal-failure: false
      dependencies:
          - fetch_test_solution_6
      cmd:
          bin: "${JUDGES_DIR}/recodex-judge-normal"
          args:
              - "6.out"
              - "plot.out"
      sandbox:
          name: "isolate"
          limits:
              - hw-group-id: group1
                parallel: 0
                chdir: ${EVAL_DIR}
                bound-directories:
                    - src: ${SOURCE_DIR}
                      dst: ${EVAL_DIR}
                      mode: RW
    - task-id: "rm_junk_test_6"
      priority: 8
      fatal-failure: false
      dependencies:
          - judging_test_6
      cmd:
          bin: "rm"
          args:
              - "${SOURCE_DIR}/kuly.in"
              - "${SOURCE_DIR}/plot.out"
              - "${SOURCE_DIR}/6.out"
...
EOS;
}

# Testing methods run
$testCase = new TestJobConfigStorage();
$testCase->run();
