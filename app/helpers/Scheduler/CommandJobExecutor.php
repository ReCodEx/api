<?php

namespace App\Helpers\Scheduler;

use App\Model\Entity\SchedulerCommandJob;
use Exception;
use Nette\DI\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Class CommandJobManager
 */
class CommandJobExecutor extends BaseJobExecutor {

  /**
   * @var Container
   */
  private $container;

  /**
   * @var Application
   */
  private $application = null;

  /**
   * CommandJobManager constructor.
   * @param Container $container
   */
  public function __construct(Container $container) {
    $this->container = $container;
  }


  public function getJobClass(): string {
    return SchedulerCommandJob::class;
  }

  /**
   * @param SchedulerCommandJob $job
   * @throws Exception
   */
  protected function internalRun($job) {
    if ($this->application === null) {
      // Attention, everyone! Nasty hack at sight!!!
      // Ok, let me explain what this is about... Circular dependency
      // The problem is that application has as dependency scheduler which
      // in return has as dependency this executor. And of course this executor
      // would like to use the services of the application which is not possible
      // with classical DI principles...
      // So I pulled a little sneaky on ya and initialize application in here
      // and not there.
      $this->application = $this->container->getService("console.application");
    }

    $args = [$job->getCommand()];
    if (!empty($job->getDecodedArguments())) {
      $args = array_merge($args, $job->getDecodedArguments());
    }

    $input = new ArrayInput($args);
    $this->application->run($input);
  }
}
