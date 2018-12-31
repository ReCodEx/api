<?php

namespace App\Helpers\Scheduler;

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
    return IJob::class; // TODO
  }

  public function run(IJob $job) {
    // TODO
  }
}
