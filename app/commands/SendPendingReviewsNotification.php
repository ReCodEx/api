<?php

namespace App\Console;

use App\Helpers\Notifications\ReviewsEmailsSender;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;

class SendPendingReviewsNotification extends Command
{
    protected static $defaultName = 'notifications:pending-reviews';

    /** @var ReviewsEmailsSender */
    private $sender;

    /** @var AssignmentSolutions */
    private $assignmentSolutions;

    /** @var string */
    private $threshold;

    public function __construct(
        string $threshold,
        ReviewsEmailsSender $sender,
        AssignmentSolutions $assignmentSolutions,
    ) {
        parent::__construct();
        $this->threshold = $threshold;
        $this->sender = $sender;
        $this->assignmentSolutions = $assignmentSolutions;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Send notifications for pending (not closed) code revirews to group admins.'
        );
    }

    private $groupAdminsCache = [];

    private function getGroupAdmins(Group $group): array
    {
        if (!array_key_exists($group->getId(), $this->groupAdminsCache)) {
        }
        return $this->groupAdminsCache[$group->getId()];
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $threshold = new DateTime();
        if ($this->threshold) {
            $threshold->modify($this->threshold); // only reviews opened before treshold are reported
        }
        $pendingReviews = $this->assignmentSolutions->findLingeringReviews($threshold);

        $pendingPerUser = [];
        $users = [];
        foreach ($pendingReviews as $solution) {
            if (!$solution->getAssignment() || !$solution->getAssignment()->getGroup()) {
                continue;
            }

            // group together per user
            $admins = $this->getGroupAdmins($solution->getAssignment()->getGroup());
            foreach ($admins as $id => $user) {
                $users[$id] = $user;

                if (empty($pendingPerUser[$id])) {
                    $pendingPerUser[$id] = [];
                }
                $pendingPerUser[$id][] = $solution;
            }
        }

        // send one email to each user
        foreach ($pendingPerUser as $id => $solutions) {
            $this->sender->notifyPendingReviews($users[$id], $solutions);
        }

        return 0;
    }
}
