<?php

namespace App\Console;

use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Repository\Assignments;
use App\Model\Repository\ShadowAssignments;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;

#[AsCommand(
    name: 'notifications:assignment-deadlines',
    description: 'Send notifications for assignments with imminent deadlines.'
)]
class SendAssignmentDeadlineNotification extends Command
{
    /** @var AssignmentEmailsSender */
    private $sender;

    /** @var Assignments */
    private $assignments;

    /** @var ShadowAssignments */
    private $shadowAssignments;

    /** @var string */
    private $thresholdFrom;

    /** @var string */
    private $thresholdTo;

    public function __construct(
        string $thresholdFrom,
        string $thresholdTo,
        Assignments $assignments,
        ShadowAssignments $shadowAssignments,
        AssignmentEmailsSender $sender
    ) {
        parent::__construct();
        $this->sender = $sender;
        $this->assignments = $assignments;
        $this->shadowAssignments = $shadowAssignments;
        $this->thresholdFrom = $thresholdFrom;
        $this->thresholdTo = $thresholdTo;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        foreach ($this->shadowAssignments->findByDeadline($from, $to) as $shadowAssignment) {
            $group = $shadowAssignment->getGroup();
            if ($shadowAssignment->isPublic() && $group && !$group->isArchived()) {
                $this->sender->shadowAssignmentDeadline($shadowAssignment);
            }
        }

        return 0;
    }
}
