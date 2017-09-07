<?php
namespace App\Console;

use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Repository\Assignments;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendAssignmentDeadlineNotification extends Command {
  /** @var AssignmentEmailsSender */
  private $sender;

  /** @var Assignments */
  private $assignments;

  /** @var string */
  private $threshold;

  public function __construct(string $threshold, Assignments $assignments, AssignmentEmailsSender $sender) {
    parent::__construct();
    $this->sender = $sender;
    $this->assignments = $assignments;
    $this->threshold = $threshold;
  }

  protected function configure() {
    $this->setName('notifications:assignment-deadlines')->setDescription('Send notifications for assignments with imminent deadlines.');
    $this->addArgument("period", InputArgument::REQUIRED, "How often is the script run (e.g. '1 day')");
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $period = $input->getArgument("period");

    $from = new DateTime();
    $from->modify("+" . $this->threshold);
    $to = clone $from;
    $to->modify("+" . $period);

    foreach ($this->assignments->findByDeadline($from, $to) as $assignment) {
      $this->sender->assignmentDeadline($assignment);
    }

    return 0;
  }
}
