<?php

include '../bootstrap.php';

use Tester\Assert;


/**
 * Job configuration builder is mostly tested in components which are
 * constructed/built by it (JobConfig, SubmissionHeader, etc...).
 * This is only general test which tests only simple cases.
 */
class TestJobConfigBuilder extends Tester\TestCase
{

  public function testTrue() {
    Assert::true(true);
  }

  static $jobConfig = <<<'EOS'
---
submission:
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
...
EOS;
}

# Testing methods run
$testCase = new TestJobConfigBuilder;
$testCase->run();
