<?php

namespace App\Helpers\Scheduler;

use App\Model\Entity\SchedulerJob;
use Exception;

/**
 * Interface IJobManager
 */
interface IJobManager {

  /**
   * @return string
   */
  public function getJobClass(): string;

  /**
   * @param SchedulerJob $job
   * @throws Exception
   */
  public function run(SchedulerJob $job);
}
