<?php

namespace App\Console;

use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Repository\Assignments;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendAssignmentDeadlineNotification extends Command
{
    protected static $defaultName = 'notifications:assignment-deadlines';

    /** @var AssignmentEmailsSender */
    private $sender;

    /** @var Assignments */
    private $assignments;

    /** @var string */
    private $thresholdFrom;

    /** @var string */
    private $thresholdTo;

    public function __construct(
        string $thresholdFrom,
        string $thresholdTo,
        Assignments $assignments,
        AssignmentEmailsSender $sender
    ) {
        parent::__construct();
        $this->sender = $sender;
        $this->assignments = $assignments;
        $this->thresholdFrom = $thresholdFrom;
        $this->thresholdTo = $thresholdTo;
    }

    protected function configure()
    {
        $this->setName('notifications:assignment-deadlines')->setDescription(
            'Send notifications for assignments with imminent deadlines.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from = new DateTime();
        if ($this->thresholdFrom) {
            $from->modify($this->thresholdFrom);
        }
        $to = new DateTime();
        if ($this->thresholdTo) {
            $to->modify($this->thresholdTo);
        }
        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;  // swap
        }

        foreach ($this->assignments->findByDeadline($from, $to) as $assignment) {
            $group = $assignment->getGroup();
            if ($assignment->isVisibleToStudents() && $group && !$group->isArchived()) {
                $this->sender->assignmentDeadline($assignment);
            }
        }

        return 0;
    }
}
