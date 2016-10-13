<?php

namespace App\Helpers\JobConfig;

use App\Helpers\JobConfig\Tasks\TaskBase;
use App\Helpers\JobConfig\Tasks\ExternalTask;
use App\Helpers\JobConfig\Tasks\InternalTask;


/**
 *
 */
class TaskFactory {
  /**
   *
   * @param array $data
   * @return TaskBase
   */
  public static function create(array $data): TaskBase {
    if (isset($data["sandbox"])) {
      return new ExternalTask($data);
    } else {
      return new InternalTask($data);
    }
  }
}
