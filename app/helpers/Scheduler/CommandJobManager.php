<?php

namespace App\Helpers\Scheduler;

use App\Model\Entity\SchedulerCommandJob;
use App\Model\Entity\SchedulerJob;
use Exception;

/**
 * Class CommandJobManager
 */
class CommandJobManager implements IJobManager {

  /**
   * CommandJobManager constructor.
   */
  public function __construct() {
  }


  public function getJobClass(): string {
    return SchedulerCommandJob::class;
  }

  public function run(SchedulerJob $job) {
    if (get_class($job) !== $this->getJobClass()) {
      throw new Exception(); // TODO
    }

    // TODO
  }
}
