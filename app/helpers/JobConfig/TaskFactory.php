<?php

namespace App\Helpers\JobConfig;

use App\Helpers\JobConfig\Tasks\TaskBase;
use App\Helpers\JobConfig\Tasks\ExternalTask;
use App\Helpers\JobConfig\Tasks\InternalTask;


/**
 * Factory for tasks creation.
 */
class TaskFactory {
  /**
   * Based on given datas it constructs internal or external task
   * and returns it.
   * @param array $data structured job configuration
   * @return TaskBase particular task representation
   */
  public static function create(array $data): TaskBase {
    if (isset($data["sandbox"])) {
      return new ExternalTask($data);
    } else {
      return new InternalTask($data);
    }
  }
}
