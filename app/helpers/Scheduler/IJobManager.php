<?php

namespace App\Helpers\Scheduler;

/**
 * Interface IJobManager
 */
interface IJobManager {

  /**
   * @return string
   */
  public function getJobClass(): string;

  /**
   * @param IJob $job
   */
  public function run(IJob $job);
}
