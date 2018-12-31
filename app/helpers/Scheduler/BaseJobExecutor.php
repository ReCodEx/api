<?php

namespace App\Helpers\Scheduler;

use App\Model\Entity\SchedulerJob;
use Exception;

/**
 * Interface IJobManager
 */
abstract class BaseJobExecutor {

  /**
   * @return string
   */
  public abstract function getJobClass(): string;

  /**
   * @param mixed $job
   * @throws Exception
   */
  protected abstract function internalRun($job);

  /**
   * @param SchedulerJob $job
   * @throws Exception
   */
  public function run(SchedulerJob $job) {
    if (get_class($job) !== $this->getJobClass()) {
      throw new Exception("Expected '{$this->getJobClass()}' job class");
    }

    $this->internalRun($job);
  }
}
