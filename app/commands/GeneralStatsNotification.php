<?php

namespace App\Console;

use App\Helpers\Notifications\GeneralStatsEmailsSender;
use App\Helpers\GeneralStatsHelper;
use App\Model\Repository\Assignments;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GeneralStatsNotification extends Command
{
    protected static $defaultName = 'notifications:general-stats';

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

    protected function configure()
    {
        $this->setName('notifications:general-stats')->setDescription(
            'Send notifications with general statistics overview for the administrator.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->emailSender->send($this->generalStats);
        return 0;
    }
}
