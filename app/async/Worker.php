<?php

namespace App\Async;

use App\Model\Entity\AsyncJob;
use App\Model\Repository\AsyncJobs;
use Nette\Utils\Arrays;
use Tracy\ILogger;
use Nette;
use Exception;
use DateTime;

/**
 * The worker is used by external bin file that is executed repeatedly by systemctl.
 * The worker executes async jobs (which are passed down via DB entity).
 * Worker listens on inotify events of a particular file, so we can prolong polling interval
 * significantly without sacrificing interactivity (but inotify is available only on Linux).
 */
class Worker
{
    use Nette\SmartObject;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var AsyncJobs
     */
    private $asyncJobs;

    /**
     * @var Notify
     */
    private $notify;

    /**
     * @var ILogger
     */
    private $logger;

    /**
     * @var int length of polling interval in seconds
     */
    private $pollingInterval;

    /**
     * @var int how many times we restart failed job before giving up
     */
    private $retries;

    /**
     * @var DateTime|null when it is time to shut down the worker and let it be restarted
     */
    private $timeToRestart = null;

    /**
     * @var int number of jobs remainig to process (once it reaches zero, the worker terminates so it can be restarted)
     */
    private $jobsRemaining = 1;

    /**
     * Terminate signal has been risen.
     * @var bool
     */
    private $terminated = false;

    /**
     * @var bool
     */
    private $quiet = false;


    public function __construct($config, Dispatcher $dispatcher, AsyncJobs $asyncJobs, ILogger $logger)
    {
        $this->dispatcher = $dispatcher;
        $this->asyncJobs = $asyncJobs;
        $this->notify = new Notify($config);
        $this->logger = $logger;

        $this->pollingInterval = max((int)Arrays::get($config, "pollingInterval", 60), 1);
        $this->retries = (int)Arrays::get($config, "retries", 3);

        // termination watchdog parameters preventing memory leaking
        $timeToRestart = Arrays::get($config, ["restartWorkerAfter", "time"], null);
        if ($timeToRestart) {
            $this->timeToRestart = new DateTime();
            $this->timeToRestart->modify("+$timeToRestart seconds");
        }

        $this->jobsRemaining = Arrays::get($config, ["restartWorkerAfter", "jobs"], 1);

        $this->quiet = (bool)Arrays::get($config, "quiet", false);

        // setup signal handling (try to terminate gracefuly on these signals)
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'terminate']);
            pcntl_signal(SIGTERM, [$this, 'terminate']);
        }
    }

    public function terminate(): void
    {
        if (!$this->quiet) {
            echo "Terminating on signal...\n";
        }
        $this->terminated = true;
        $this->dispatcher->cancel();
    }

    /**
     * Test whether the worker shall process jobs further, or whether it should go for restart.
     * @return bool true if it shoud continue
     */
    private function shallContinue(): bool
    {
        return !$this->terminated && $this->jobsRemaining > 0
            && (!$this->timeToRestart || $this->timeToRestart > new DateTime());
    }

    /**
     * Gets the most relevant async job record from the database and allocate it for execution.
     * The job is branded with worker ID and its retries count is increased.
     */
    private function getNextJob(string $workerId): ?AsyncJob
    {
        $this->asyncJobs->beginTransaction();
        try {
            // get jobs in the actual order they should be executed
            $jobs = $this->asyncJobs->findAllReadyForExecution($this->pollingInterval, $workerId);
            if (!$jobs) {
                $this->asyncJobs->rollback();
                return null;
            }

            $selectedJob = reset($jobs); // default is the first job returned from repository
            foreach ($jobs as $job) {
                if ($job->getScheduledAt() && $this->getJobDispatchDelay($job) === 0) {
                    // but if we have a scheduled job that already should have been executed...
                    $selectedJob = $job; // ... we make it a priority!
                    break;
                }
            }

            $selectedJob->allocateForWorker($workerId);
            $this->asyncJobs->persist($selectedJob);
            $this->asyncJobs->commit();
        } catch (Exception $e) {
            $this->asyncJobs->rollback();
            throw $e;
        }
        return $selectedJob;
    }

    /**
     * Computes after how many seconds a job should be executed.
     * @return int number of seconds (0 = immediately)
     */
    private function getJobDispatchDelay(AsyncJob $job): int
    {
        if ($job->getScheduledAt()) {
            $delay = $job->getScheduledAt()->getTimestamp() - time();
            return max($delay, 0); // past jobs are represented as immediate jobs
        } else {
            return 0; // other jobs are scheduled immediately
        }
    }

    /**
     * Process an actual async job.
     * @param AsyncJob $job to be processed
     */
    private function dispatchJob(AsyncJob $job)
    {
        if ($job->getRetries() > $this->retries) {
            // this job has been beaten to death...
            $job->setFinishedNow();
            $this->asyncJobs->persist($job);
            $this->asyncJobs->flush();
            return;
        }

        --$this->jobsRemaining; // this counter goes down even in case of failure

        try {
            $this->dispatcher->dispatch($job);
            $job->setFinishedNow();
        } catch (Exception $e) {
            $job->appendError($e->getMessage());
            $this->logger->log(
                sprintf("Job '%s' failed (try #%d): %s", $job->getCommand(), $job->getRetries(), $e->getMessage()),
                ILogger::ERROR
            );
        }

        $this->asyncJobs->persist($job);
        $this->asyncJobs->flush();
    }

    /**
     * Main method of the worker that actually processes the async job.
     * This method is blockinig, once it terminates the worker process should also terminate.
     * @param string $workerId identifier of the worker (should be gathered from the CLI arguments)
     */
    public function run(string $workerId)
    {
        $this->notify->init(); // start listeining for notifications

        while ($this->shallContinue()) { // just a precaution so the worker will not run forever
            // allocate and mark the best suitable job (null = none available)
            $job = $this->getNextJob($workerId);
            if ($job && !$this->terminated) {
                // when this job should be executed (in how many seconds)?
                $timeout = $this->getJobDispatchDelay($job);
                if ($timeout === 0) { // immediately => let's do it!
                    $this->dispatchJob($job);
                }
            } else {
                // no job on the horizon => sleep for the maximal polling time
                $timeout = $this->pollingInterval;
            }

            if ($timeout > 0 && !$this->terminated) {
                // some time to spare => let's take a nap!
                $this->notify->waitForNotification($timeout);
            }
        }

        $this->notify->clear();
        if (!$this->quiet) {
            echo "Shutdown.\n";
        }
    }
}
