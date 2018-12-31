<?php

namespace App\Helpers\Scheduler;

use App\Model\Entity\SchedulerCommandJob;
use App\Model\Entity\SchedulerJob;

/**
 * Class CommandJobManager
 */
class CommandJobExecutor extends BaseJobExecutor {

  /**
   * CommandJobManager constructor.
   */
  public function __construct() {
  }


  public function getJobClass(): string {
    return SchedulerCommandJob::class;
  }

  protected function internalRun(SchedulerJob $job) {
    // TODO
  }
}
