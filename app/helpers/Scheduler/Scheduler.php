<?php

namespace App\Helpers\Scheduler;

use App\Model\Repository\SchedulerJobs;

/**
 * Class Scheduler
 */
class Scheduler {

  /**
   * @var SchedulerJobs
   */
  private $schedulerJobs;

  /**
   * Indexed by Job class identifier
   * @var IJobManager[]
   */
  private $managers = [];

  /**
   * Scheduler constructor.
   * @param SchedulerJobs $schedulerJobs
   * @param CommandJobManager $commandJobManager
   */
  public function __construct(SchedulerJobs $schedulerJobs, CommandJobManager $commandJobManager) {
    $this->schedulerJobs = $schedulerJobs;
    $this->managers[$commandJobManager->getJobClass()] = $commandJobManager;
  }


  public function run() {
    // TODO
  }
}
