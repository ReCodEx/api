<?php

namespace App\Helpers\Scheduler;

/**
 * Class Scheduler
 */
class Scheduler {

  /**
   * Indexed by Job class identifier
   * @var IJobManager[]
   */
  private $managers = [];

  /**
   * Scheduler constructor.
   * @param CommandJobManager $commandJobManager
   */
  public function __construct(CommandJobManager $commandJobManager) {
    $this->managers[$commandJobManager->getJobClass()] = $commandJobManager;
  }


  public function run() {
    // TODO
  }
}
