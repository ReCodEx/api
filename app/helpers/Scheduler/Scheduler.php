<?php

namespace App\Helpers\Scheduler;

use App\Model\Entity\SchedulerJob;
use App\Model\Repository\SchedulerJobs;
use DateInterval;
use Exception;
use DateTime;

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
   * @var BaseJobExecutor[]
   */
  private $managers = [];

  /**
   * Scheduler constructor.
   * @param SchedulerJobs $schedulerJobs
   * @param CommandJobExecutor $commandJobManager
   */
  public function __construct(SchedulerJobs $schedulerJobs, CommandJobExecutor $commandJobManager) {
    $this->schedulerJobs = $schedulerJobs;
    $this->managers[$commandJobManager->getJobClass()] = $commandJobManager;
  }


  /**
   * Get next execution point for given scheduler job.
   * @param SchedulerJob $job
   * @return DateTime
   * @throws Exception
   */
  private function getNextJobExecution(SchedulerJob $job): DateTime {
    $next = $job->getNextExecution();
    $now = new DateTime();

    while ($next <= $now) {
      $next->add(new DateInterval("PT{$job->getDelay()}S"));
    }

    return $next;
  }

  /**
   *
   * @throws Exception
   */
  public function run() {
    /** @var SchedulerJob[] $jobs */
    $jobs = $this->schedulerJobs->findAllReadyForExecution();
    foreach ($jobs as $job) {
      $jobClass = get_class($job);
      if (!array_key_exists($jobClass, $this->managers)) {
        // TODO: fail?
        continue;
      }

      try {
        // Run, job... Run...
        $manager = $this->managers[$jobClass];
        $manager->run($job);
      } catch (Exception $e) {
        // TODO: write failure message into database
      }

      // update some job information
      $job->executedNow();

      // if entity is repeatable schedule it, if not delete it
      if ($job->getDelay() !== null) {
        // job is repeatable... schedule its next execution
        try {
          $job->setNextExecution($this->getNextJobExecution($job));
        } catch (Exception $e) {
          // TODO: failed to find next execution point
        }
      } else {
        // job is not repeatable... delete it
        $job->deletedNow();
      }
    }

    $this->schedulerJobs->flush();
  }
}
