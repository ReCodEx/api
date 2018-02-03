<?php

namespace App\Helpers\JobConfig;

use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\Tasks\Task;

/**
 * Loader service which is able to load job configuration in the right format
 * and with the all mandatory fields into internal holders (JobConfig, etc...).
 * Given data are checked against mandatory fields and in case of error exception is thrown.
 */
class Loader {

  /**
   * Build and check submission header from given structured data.
   * @param array $data
   * @return SubmissionHeader
   * @throws JobConfigLoadingException
   */
  public function loadSubmissionHeader($data): SubmissionHeader {
    $header = new SubmissionHeader;

    if (!isset($data[SubmissionHeader::JOB_ID_KEY])) {
      throw new JobConfigLoadingException("Submission header does not contain the required field '" . SubmissionHeader::JOB_ID_KEY . "'");
    }
    $header->setJobId($data[SubmissionHeader::JOB_ID_KEY]);
    unset($data[SubmissionHeader::JOB_ID_KEY]);

    if (!isset($data[SubmissionHeader::HARDWARE_GROUPS_KEY])) {
      throw new JobConfigLoadingException("Submission header does not contain the required field '" . SubmissionHeader::HARDWARE_GROUPS_KEY . "'");
    } else if (!is_array($data[SubmissionHeader::HARDWARE_GROUPS_KEY])) {
      throw new JobConfigLoadingException("Submission header field '" . SubmissionHeader::HARDWARE_GROUPS_KEY . "' does not contain an array");
    }
    $header->setHardwareGroups($data[SubmissionHeader::HARDWARE_GROUPS_KEY]);
    unset($data[SubmissionHeader::HARDWARE_GROUPS_KEY]);

    if (isset($data[SubmissionHeader::FILE_COLLECTOR_KEY])) {
      $header->setFileCollector($data[SubmissionHeader::FILE_COLLECTOR_KEY]);
      unset($data[SubmissionHeader::FILE_COLLECTOR_KEY]);
    }

    if (isset($data[SubmissionHeader::LOG_KEY])) {
      $header->setLog(filter_var($data[SubmissionHeader::LOG_KEY], FILTER_VALIDATE_BOOLEAN));
      unset($data[SubmissionHeader::LOG_KEY]);
    }

    $header->setAdditionalData($data);
    return $header;
  }

  /**
   * Build and check bound directory configuration from given structured data.
   * @param array $data
   * @param string $taskId Task identifier (name) for better error messages
   * @return BoundDirectoryConfig
   * @throws JobConfigLoadingException
   */
  public function loadBoundDirectoryConfig($data, $taskId = ""): BoundDirectoryConfig {
    $boundDir = new BoundDirectoryConfig;

    if (!isset($data[BoundDirectoryConfig::SRC_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "': bound directory does not contain required field '" . BoundDirectoryConfig::SRC_KEY . "'");
    }
    $boundDir->setSource($data[BoundDirectoryConfig::SRC_KEY]);
    unset($data[BoundDirectoryConfig::SRC_KEY]);

    if (!isset($data[BoundDirectoryConfig::DST_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "': bound directory does not contain required field '" . BoundDirectoryConfig::DST_KEY . "'");
    }
    $boundDir->setDestination($data[BoundDirectoryConfig::DST_KEY]);
    unset($data[BoundDirectoryConfig::DST_KEY]);

    if (!isset($data[BoundDirectoryConfig::MODE_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "': bound directory does not contain required field '" . BoundDirectoryConfig::MODE_KEY . "'");
    }
    $boundDir->setMode($data[BoundDirectoryConfig::MODE_KEY]);
    unset($data[BoundDirectoryConfig::MODE_KEY]);

    // *** LOAD REMAINING DATA
    $boundDir->setAdditionalData($data);
    return $boundDir;
  }

  /**
   * Builds and checks limits from given structured data.
   * @param array $data
   * @param string $taskId Task identifier (name) for better error messages
   * @return Limits
   * @throws JobConfigLoadingException
   */
  public function loadLimits($data, $taskId = ""): Limits {
    $limits = new Limits;

    if (!is_array($data)) {
      throw new JobConfigLoadingException("Task '" . $taskId . "': limits are not array");
    }

    if (!isset($data[Limits::HW_GROUP_ID_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "': sandbox limits section does not contain required field '" . Limits::HW_GROUP_ID_KEY . "'");
    }
    $limits->setId($data[Limits::HW_GROUP_ID_KEY]);
    unset($data[Limits::HW_GROUP_ID_KEY]);

    // *** LOAD OPTIONAL DATAS

    if (isset($data[Limits::TIME_KEY])) {
      $limits->setTimeLimit(floatval($data[Limits::TIME_KEY]));
      unset($data[Limits::TIME_KEY]);
    }

    if (isset($data[Limits::WALL_TIME_KEY])) {
      $limits->setWallTime(floatval($data[Limits::WALL_TIME_KEY]));
      unset($data[Limits::WALL_TIME_KEY]);
    }

    if (isset($data[Limits::EXTRA_TIME_KEY])) {
      $limits->setExtraTime(floatval($data[Limits::EXTRA_TIME_KEY]));
      unset($data[Limits::EXTRA_TIME_KEY]);
    }

    if (isset($data[Limits::STACK_SIZE_KEY])) {
      $limits->setStackSize(intval($data[Limits::STACK_SIZE_KEY]));
      unset($data[Limits::STACK_SIZE_KEY]);
    }

    if (isset($data[Limits::MEMORY_KEY])) {
      $limits->setMemoryLimit(intval($data[Limits::MEMORY_KEY]));
      unset($data[Limits::MEMORY_KEY]);
    }

    if (isset($data[Limits::PARALLEL_KEY])) {
      $limits->setParallel(intval($data[Limits::PARALLEL_KEY]));
      unset($data[Limits::PARALLEL_KEY]);
    }

    if (isset($data[Limits::DISK_SIZE_KEY])) {
      $limits->setDiskSize(intval($data[Limits::DISK_SIZE_KEY]));
      unset($data[Limits::DISK_SIZE_KEY]);
    }

    if (isset($data[Limits::DISK_FILES_KEY])) {
      $limits->setDiskFiles(intval($data[Limits::DISK_FILES_KEY]));
      unset($data[Limits::DISK_FILES_KEY]);
    }

    if (isset($data[Limits::ENVIRON_KEY]) && is_array($data[Limits::ENVIRON_KEY])) {
      $limits->setEnvironVariables($data[Limits::ENVIRON_KEY]);
      unset($data[Limits::ENVIRON_KEY]);
    }

    if (isset($data[Limits::BOUND_DIRECTORIES_KEY]) && is_array($data[Limits::BOUND_DIRECTORIES_KEY])) {
      foreach ($data[Limits::BOUND_DIRECTORIES_KEY] as $dir) {
        $limits->addBoundDirectory($this->loadBoundDirectoryConfig($dir, $taskId));
      }
      unset($data[Limits::BOUND_DIRECTORIES_KEY]);
    }

    // *** LOAD REMAINING INFO

    $limits->setAdditionalData($data);
    return $limits;
  }

  /**
   * Build and check sandbox configuration from given structured data.
   * @param array $data
   * @param string $taskId Task identifier (name) for better error messages
   * @return SandboxConfig
   * @throws JobConfigLoadingException
   */
  public function loadSandboxConfig($data, $taskId = ""): SandboxConfig {
    $sandboxConfig = new SandboxConfig;

    if (!isset($data[SandboxConfig::NAME_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "': sandbox section does not contain required field '" . SandboxConfig::NAME_KEY . "'");
    }
    $sandboxConfig->setName($data[SandboxConfig::NAME_KEY]);
    unset($data[SandboxConfig::NAME_KEY]);

    // *** LOAD OPTIONAL ITEMS

    if (isset($data[SandboxConfig::STDIN_KEY])) {
      $sandboxConfig->setStdin($data[SandboxConfig::STDIN_KEY]);
      unset($data[SandboxConfig::STDIN_KEY]);
    }

    if (isset($data[SandboxConfig::STDOUT_KEY])) {
      $sandboxConfig->setStdout($data[SandboxConfig::STDOUT_KEY]);
      unset($data[SandboxConfig::STDOUT_KEY]);
    }

    if (isset($data[SandboxConfig::STDERR_KEY])) {
      $sandboxConfig->setStderr($data[SandboxConfig::STDERR_KEY]);
      unset($data[SandboxConfig::STDERR_KEY]);
    }

    if (isset($data[SandboxConfig::OUTPUT_KEY])) {
      $sandboxConfig->setOutput($data[SandboxConfig::OUTPUT_KEY]);
      unset($data[SandboxConfig::OUTPUT_KEY]);
    }

    if (isset($data[SandboxConfig::CHDIR_KEY])) {
      $sandboxConfig->setChdir(strval($data[SandboxConfig::CHDIR_KEY]));
      unset($data[SandboxConfig::CHDIR_KEY]);
    }

    // *** CONSTRUCT ALL LIMITS

    if (isset($data[SandboxConfig::LIMITS_KEY]) && is_array($data[SandboxConfig::LIMITS_KEY])) {
      foreach ($data[SandboxConfig::LIMITS_KEY] as $lim) {
        $sandboxConfig->setLimits($this->loadLimits($lim, $taskId));
      }
    } elseif (isset($data[SandboxConfig::LIMITS_KEY]) && !is_array($data[SandboxConfig::LIMITS_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "': list of limits is not array");
    }

    // *** LOAD ALL REMAINING INFO
    $sandboxConfig->setAdditionalData($data);
    return $sandboxConfig;
  }

  /**
   * Build and check task configuration from given structured data.
   * @param array $data
   * @return Task
   * @throws JobConfigLoadingException
   */
  public function loadTask($data): Task {
    $task = new Task;
    $taskId = "";

    // *** LOAD MANDATORY ITEMS

    if (!isset($data[Task::TASK_ID_KEY])) {
      throw new JobConfigLoadingException("Task configuration does not contain required field '" . Task::TASK_ID_KEY . "'");
    }
    $task->setId($data[Task::TASK_ID_KEY]);
    $taskId = $task->getId();
    unset($data[Task::TASK_ID_KEY]);

    if (!isset($data[Task::CMD_KEY]) || !is_array($data[Task::CMD_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "' does not contain proper field '" . Task::CMD_KEY . "'");
    }

    if (!isset($data[Task::CMD_KEY][Task::CMD_BIN_KEY])) {
      throw new JobConfigLoadingException("Task '" . $taskId . "' does not contain required field '" . Task::CMD_KEY . "." . Task::CMD_BIN_KEY . "'");
    }
    $task->setCommandBinary($data[Task::CMD_KEY][Task::CMD_BIN_KEY]);
    unset($data[Task::CMD_KEY][Task::CMD_BIN_KEY]);

    // *** LOAD OPTIONAL ITEMS

    if (isset($data[Task::PRIORITY_KEY])) {
      $task->setPriority(intval($data[Task::PRIORITY_KEY]));
      unset($data[Task::PRIORITY_KEY]);
    } else {
      $task->setPriority(1);
    }

    if (isset($data[Task::FATAL_FAILURE_KEY])) {
      $task->setFatalFailure(filter_var($data[Task::FATAL_FAILURE_KEY], FILTER_VALIDATE_BOOLEAN));
      unset($data[Task::FATAL_FAILURE_KEY]);
    } else {
      $task->setFatalFailure(false);
    }

    if (isset($data[Task::DEPENDENCIES]) && is_array($data[Task::DEPENDENCIES])) {
      $task->setDependencies($data[Task::DEPENDENCIES]);
      unset($data[Task::DEPENDENCIES]);
    }

    if (isset($data[Task::CMD_KEY][Task::CMD_ARGS_KEY]) && is_array($data[Task::CMD_KEY][Task::CMD_ARGS_KEY])) {
      $task->setCommandArguments($data[Task::CMD_KEY][Task::CMD_ARGS_KEY]);
      unset($data[Task::CMD_KEY][Task::CMD_ARGS_KEY]);
    }

    if (isset($data[Task::TYPE_KEY])) {
      $task->setType($data[Task::TYPE_KEY]);
      unset($data[Task::TYPE_KEY]);
    }

    if (isset($data[Task::TEST_ID_KEY])) {
      $task->setTestId($data[Task::TEST_ID_KEY]);
      unset($data[Task::TEST_ID_KEY]);
    }

    if (isset($data[Task::SANDBOX_KEY])) {
      $task->setSandboxConfig($this->loadSandboxConfig($data[Task::SANDBOX_KEY], $taskId));
      unset($data[Task::SANDBOX_KEY]);
    }

    // *** LOAD REMAINING DATA
    $task->setAdditionalData($data);
    return $task;
  }

  /**
   * Build and check job configuration from given structured data.
   * @param array $data
   * @param bool $humanReadable
   * @return JobConfig
   * @throws JobConfigLoadingException
   */
  public function loadJobConfig($data, $humanReadable = false): JobConfig {
    $config = new JobConfig($humanReadable);

    if (!is_array($data)) {
      throw new JobConfigLoadingException("Job configuration is not in correct format");
    }

    // parse and build submission header
    if (!isset($data[JobConfig::SUBMISSION_KEY])) {
      throw new JobConfigLoadingException("Job config does not contain the required field '" . JobConfig::SUBMISSION_KEY . "'");
    }
    $config->setSubmissionHeader($this->loadSubmissionHeader($data[JobConfig::SUBMISSION_KEY]));
    unset($data[JobConfig::SUBMISSION_KEY]);

    // parse and build list of tasks
    if (!isset($data[JobConfig::TASKS_KEY])) {
      throw new JobConfigLoadingException("Job config does not contain the required field '" . JobConfig::TASKS_KEY . "'");
    }
    foreach ($data[JobConfig::TASKS_KEY] as $taskConfig) {
      $config->addTask($this->loadTask($taskConfig));
    }
    unset($data[JobConfig::TASKS_KEY]);

    // finally maintain forward compatibility
    $config->setAdditionalData($data);
    return $config;
  }
}
