<?php

include '../bootstrap.php';

use Tester\Assert;

use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Storage as JobConfigStorage;

use App\Helpers\EvaluationResults\Loader;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\TaskResult;
use App\Helpers\EvaluationResults\TestResult;
use App\Helpers\EvaluationResults\SkippedTestResult;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;
use App\Exceptions\SubmissionEvaluationFailedException;

class TestEvaluationResultsLoader extends Tester\TestCase
{

  public function testCanLoadSuccessResult() {
    $jobConfig = JobConfigStorage::parseJobConfig(self::$jobConfig);
    $results = Loader::parseResults(self::$successResult, $jobConfig);
    Assert::type(EvaluationResults::CLASS, $results);
  }

  public function testCanLoadInitFailedResult() {
    $jobConfig = JobConfigStorage::parseJobConfig(self::$jobConfig);
    $results = Loader::parseResults(self::$initFailedResult, $jobConfig);
    Assert::type(EvaluationResults::CLASS, $results);
    Assert::false($results->initOK());
    Assert::equal(6, count($results->getTestsResults("group1")));
    foreach ($results->getTestsResults("group1") as $testResult) {
      Assert::type(SkippedTestResult::CLASS, $testResult);
    }
  }

  public function testCanLoadFailedResult() {
    $jobConfig = JobConfigStorage::parseJobConfig(self::$jobConfig);
    $results = Loader::parseResults(self::$failedResult, $jobConfig);
    Assert::type(EvaluationResults::CLASS, $results);
  }

  public function testRejectsInvalidYaml() {
    $jobConfig = JobConfigStorage::parseJobConfig(self::$jobConfig);
    Assert::exception(function() use ($jobConfig) {
      Loader::parseResults('
a:
b:
    - c
      ', $jobConfig);
    }, SubmissionEvaluationFailedException::CLASS);
  }

  public function testCorrectInterpretation() {
    $jobConfig = JobConfigStorage::parseJobConfig(self::$jobConfig);
    $results = Loader::parseResults(self::$successResult, $jobConfig);
    Assert::true($results->initOK());
    Assert::equal(6, count($results->getTestsResults("group1")));
  }

  public function testCorrectInterpretationOfFailedSubmission() {
    $jobConfig = JobConfigStorage::parseJobConfig(self::$jobConfig);
    $results = Loader::parseResults(self::$failedResult, $jobConfig);
    Assert::true($results->initOK());
    Assert::equal(6, count($results->getTestsResults("group1")));
    foreach ($results->getTestsResults("group1") as $result) {
      Assert::type(SkippedTestResult::CLASS, $result);
    }
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
        - group1
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

    static $successResult = <<<'EOS'
job-id: hippoes
results:
    - { status: OK, task-id: compilation, sandbox_results: { message: '', memory: 5996, exitsig: 0, wall-time: 0.187, max-rss: 20968, status: OK, exitcode: 0, time: 0.055, killed: false } }
    - { task-id: fetch_test_1, status: OK }
    - { sandbox_results: { status: OK, exitsig: 0, max-rss: 1380, killed: false, message: '', time: 0.001, memory: 128, wall-time: 0.063, exitcode: 0 }, task-id: evaluation_test_1, status: OK }
    - { task-id: fetch_test_solution_1, status: OK }
    - { task-id: judging_test_1, sandbox_results: { status: OK, exitsig: 0, killed: false, message: '', time: 0.001, wall-time: 0.078, max-rss: 1332, memory: 128, exitcode: 0 }, status: OK }
    - { task-id: rm_junk_test_1, status: OK }
    - { task-id: fetch_test_2, status: OK }
    - { task-id: evaluation_test_2, status: OK, sandbox_results: { max-rss: 1380, status: OK, exitsig: 0, killed: false, message: '', exitcode: 0, time: 0, wall-time: 0.067, memory: 128 } }
    - { task-id: fetch_test_solution_2, status: OK }
    - { task-id: judging_test_2, status: OK, sandbox_results: { exitsig: 0, killed: false, message: '', exitcode: 0, time: 0.001, wall-time: 0.062, memory: 128, max-rss: 1332, status: OK } }
    - { task-id: rm_junk_test_2, status: OK }
    - { task-id: fetch_test_3, status: OK }
    - { sandbox_results: { message: '', exitcode: 0, wall-time: 0.047, memory: 128, max-rss: 1380, status: OK, exitsig: 0, killed: false, time: 0 }, status: OK, task-id: evaluation_test_3 }
    - { status: OK, task-id: fetch_test_solution_3 }
    - { task-id: judging_test_3, status: OK, sandbox_results: { exitcode: 0, wall-time: 0.047, time: 0.001, memory: 128, max-rss: 1332, status: OK, killed: false, exitsig: 0, message: '' } }
    - { task-id: rm_junk_test_3, status: OK }
    - { task-id: fetch_test_4, status: OK }
    - { task-id: evaluation_test_4, status: OK, sandbox_results: { exitcode: 0, time: 0.004, wall-time: 0.091, memory: 256, max-rss: 1460, status: OK, exitsig: 0, killed: false, message: '' } }
    - { status: OK, task-id: fetch_test_solution_4 }
    - { task-id: judging_test_4, status: OK, sandbox_results: { exitcode: 0, time: 0.001, wall-time: 0.094, memory: 128, max-rss: 1332, status: OK, exitsig: 0, killed: false, message: '' } }
    - { task-id: rm_junk_test_4, status: OK }
    - { task-id: fetch_test_5, status: OK }
    - { task-id: evaluation_test_5, status: OK, sandbox_results: { exitcode: 0, time: 0.033, wall-time: 0.094, memory: 896, max-rss: 1984, status: OK, killed: false, exitsig: 0, message: '' } }
    - { task-id: fetch_test_solution_5, status: OK }
    - { status: OK, sandbox_results: { exitcode: 0, time: 0.001, wall-time: 0.078, memory: 128, max-rss: 1332, status: OK, killed: false, exitsig: 0, message: '' }, task-id: judging_test_5 }
    - { task-id: rm_junk_test_5, status: OK }
    - { task-id: fetch_test_6, status: OK }
    - { sandbox_results: { exitcode: 0, time: 0.137, wall-time: 0.206, memory: 3328, max-rss: 4360, status: OK, exitsig: 0, killed: false, message: '' }, task-id: evaluation_test_6, status: OK }
    - { task-id: fetch_test_solution_6, status: OK }
    - { task-id: judging_test_6, status: OK, sandbox_results: { exitcode: 0, time: 0.001, wall-time: 0.075, memory: 256, max-rss: 1332, status: OK, killed: false, exitsig: 0, message: '' } }
    - { task-id: rm_junk_test_6, status: OK }
EOS;

    static $initFailedResult = <<<'EOS'
results:
    - { status: FAILED, sandbox_results: { killed: false, status: OK, wall-time: 0.08, message: '', max-rss: 9944, memory: 6508, exitcode: 0, time: 0.072, exitsig: 0 }, task-id: compilation }
job-id: hippoes
EOS;

    static $failedResult = <<<'EOS'
results:
    - { status: OK, sandbox_results: { killed: false, status: OK, wall-time: 0.08, message: '', max-rss: 9944, memory: 6508, exitcode: 0, time: 0.072, exitsig: 0 }, task-id: compilation }
    - { status: FAILED, error_message: 'Cannot fetch files. Error: Failed to download http://localhost:9999/tasks/b5cc16b8ca08dc099b50768999202ae3807f60c6 to /tmp/recodex/eval/1/491d1492-72c0-11e6-a6fa-9883c65865f3/kuly.in. Error: Couldn''t connect to server', task-id: fetch_test_1 }
    - { task-id: evaluation_test_1, status: SKIPPED }
    - { status: SKIPPED, task-id: fetch_test_solution_1 }
    - { status: SKIPPED, task-id: judging_test_1 }
    - { task-id: rm_junk_test_1, status: SKIPPED }
    - { task-id: fetch_test_2, status: FAILED, error_message: 'Cannot fetch files. Error: Failed to download http://localhost:9999/tasks/2ac6ba84d1c016019bed328abe1fac06c3efbe6d to /tmp/recodex/eval/1/491d1492-72c0-11e6-a6fa-9883c65865f3/kuly.in. Error: Couldn''t connect to server' }
    - { task-id: evaluation_test_2, status: SKIPPED }
    - { task-id: fetch_test_solution_2, status: SKIPPED }
    - { task-id: judging_test_2, status: SKIPPED }
    - { task-id: rm_junk_test_2, status: SKIPPED }
    - { status: FAILED, task-id: fetch_test_3, error_message: 'Cannot fetch files. Error: Failed to download http://localhost:9999/tasks/f1666f92686d843f93f6f66ca1081016d404af49 to /tmp/recodex/eval/1/491d1492-72c0-11e6-a6fa-9883c65865f3/kuly.in. Error: Couldn''t connect to server' }
    - { task-id: evaluation_test_3, status: SKIPPED }
    - { status: SKIPPED, task-id: fetch_test_solution_3 }
    - { status: SKIPPED, task-id: judging_test_3 }
    - { status: SKIPPED, task-id: rm_junk_test_3 }
    - { status: FAILED, task-id: fetch_test_4, error_message: 'Cannot fetch files. Error: Failed to download http://localhost:9999/tasks/9c677f28e20a03d429d58186e3b38baba2fddeb5 to /tmp/recodex/eval/1/491d1492-72c0-11e6-a6fa-9883c65865f3/kuly.in. Error: Couldn''t connect to server' }
    - { task-id: evaluation_test_4, status: SKIPPED }
    - { task-id: fetch_test_solution_4, status: SKIPPED }
    - { task-id: judging_test_4, status: SKIPPED }
    - { task-id: rm_junk_test_4, status: SKIPPED }
    - { error_message: 'Cannot fetch files. Error: Failed to download http://localhost:9999/tasks/7614357c5e826690abbb74548cc2b2a3b81842bd to /tmp/recodex/eval/1/491d1492-72c0-11e6-a6fa-9883c65865f3/kuly.in. Error: Couldn''t connect to server', status: FAILED, task-id: fetch_test_5 }
    - { task-id: evaluation_test_5, status: SKIPPED }
    - { task-id: fetch_test_solution_5, status: SKIPPED }
    - { status: SKIPPED, task-id: judging_test_5 }
    - { task-id: rm_junk_test_5, status: SKIPPED }
    - { error_message: 'Cannot fetch files. Error: Failed to download http://localhost:9999/tasks/4db3be81376f812535b0d241ced5b445ff16f39f to /tmp/recodex/eval/1/491d1492-72c0-11e6-a6fa-9883c65865f3/kuly.in. Error: Couldn''t connect to server', task-id: fetch_test_6, status: FAILED }
    - { task-id: evaluation_test_6, status: SKIPPED }
    - { task-id: fetch_test_solution_6, status: SKIPPED }
    - { task-id: judging_test_6, status: SKIPPED }
    - { task-id: rm_junk_test_6, status: SKIPPED }
job-id: hippoes
EOS;

}

# Testing methods run
$testCase = new TestEvaluationResultsLoader;
$testCase->run();
