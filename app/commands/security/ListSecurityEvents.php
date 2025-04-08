<?php

namespace App\Console;

use App\Model\Entity\SecurityEvent;
use App\Model\Repository\SecurityEvents;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * List security events (login, token refresh, ...) from given time interval.
 * Events are printed to stdout in CSV format.
 */
class ListSecurityEvents extends BaseCommand
{
    protected static $defaultName = 'sec:securityEvents';

    protected static $csvDelimiter = ',';
    protected static $csvColumns = [
        'id',
        'created_at',
        'type',
        'remote_addr',
        'user_id',
        'user_first_name',
        'user_last_name',
        'user_email',
        'user_external_ids',
        'data',
    ];

    /** @var SecurityEvents */
    protected $events;

    public function __construct(SecurityEvents $events)
    {
        parent::__construct();
        $this->events = $events;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)
            ->setDescription('List all security events in given date/time interval in CSV format.');
        $this->addOption(
            'from',
            null,
            InputOption::VALUE_REQUIRED,
            'Date/time as a string acceptable by PHP DateTime constructor.',
            null
        );
        $this->addOption(
            'to',
            null,
            InputOption::VALUE_REQUIRED,
            'Date/time as a string acceptable by PHP DateTime constructor.',
            null
        );
    }

    /**
     * Extract data from objects into assoc. array ready for serialization.
     * @param SecurityEvent $event to be extracted
     * @return array where keys correspond to self::$csvColumns
     */
    protected function getEventData(SecurityEvent $event): array
    {
        $user = $event->getUser();
        return [
            'id' => $event->getId(),
            'created_at' => $event->getCreatedAt()->getTimestamp(),
            'type' => $event->getType(),
            'remote_addr' => $event->getRemoteAddr(),
            'user_id' => $user->getId(),
            'user_first_name' => $user->getFirstName(),
            'user_last_name' => $user->getLastName(),
            'user_email' => $user->getEmail(),
            'user_external_ids' => json_encode($user->getConsolidatedExternalLogins()),
            'data' => $event->getDataRaw(),
        ];
    }

    protected function printCsvLine(array $data)
    {
        static $fp = null;
        if ($fp === null) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, self::$csvColumns, self::$csvDelimiter);
        }

        // put the data in the right order
        $line = [];
        foreach (self::$csvColumns as $col) {
            $line[] = $data[$col] ?? null;
        }
        fputcsv($fp, $line, self::$csvDelimiter);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // just to save time (we do not have to pass this down to every other method invoked)
        $this->input = $input;
        $this->output = $output;

        try {
            $from = $this->getDateTimeOption('from');
            $to = $this->getDateTimeOption('to');
        } catch (Exception $e) {
            return Command::FAILURE;
        }

        $events = $this->events->findByCreatedAt($from, $to);

        foreach ($events as $event) {
            $data = $this->getEventData($event);
            $this->printCsvLine($data);
        }

        return Command::SUCCESS;
    }
}
