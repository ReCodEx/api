<?php

namespace App\Console;

use App\Helpers\Notifications\GeneralStatsEmailsSender;
use App\Helpers\GeneralStatsHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'notifications:general-stats',
    description: 'Send notifications with general statistics overview for the administrator.'
)]
class GeneralStatsNotification extends Command
{
    /** @var GeneralStatsEmailsSender */
    private $emailSender;

    /** @var GeneralStatsHelper */
    private $generalStats;

    public function __construct(GeneralStatsEmailsSender $emailSender, GeneralStatsHelper $generalStats)
    {
        parent::__construct();
        $this->emailSender = $emailSender;
        $this->generalStats = $generalStats;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->emailSender->send($this->generalStats);
        return 0;
    }
}
