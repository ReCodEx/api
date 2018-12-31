<?php

namespace App\Console;

use App\Helpers\Scheduler\Scheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SchedulerCommand extends Command {

  /** @var Scheduler */
  private $scheduler;

  public function __construct(Scheduler $scheduler) {
    parent::__construct();
    $this->scheduler = $scheduler;
  }


  protected function configure() {
    $this->setName('scheduler:run')->setDescription('Run internal scheduler which takes jobs from database.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->scheduler->run();
  }
}
