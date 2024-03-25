<?php

namespace App\Console;

use App\Model\Entity\GroupExamLock;
use App\Model\Repository\GroupExamLocks;
use DateTime;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Lists all group exam lock records that occured in given time interval.
 * Locks are listed with related data in CSV format on the stdout.
 */
class ListExamEvents extends BaseCommand
{
    protected static $defaultName = 'sec:examEvents';

    protected static $csvDelimiter = ',';
    protected static $csvColumns = [
        'id',
        'exam_id',
        'group_id',
        'user_id',
        'user_first_name',
        'user_last_name',
        'user_email',
        'user_external_ids',
        'locked_at',
        'unlocked_at',
        'remote_addr',
        'exam_begin_at',
        'exam_end_at',
        'lock_strict',
    ];

    /** @var GroupExamLocks */
    protected $examLocks;

    public function __construct(GroupExamLocks $examLocks)
    {
        parent::__construct();
        $this->examLocks = $examLocks;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)
            ->setDescription('List all exam events in given date/time interval in CSV format.');
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
     * @param GroupExamLock $lock to be extracted
     * @return array where keys correspond to self::$csvColumns
     */
    protected function getLockData(GroupExamLock $lock): array
    {
        $user = $lock->getStudent();
        return [
            'id' => $lock->getId(),
            'exam_id' => $lock->getGroupExam()->getId(),
            'group_id' => $lock->getGroupExam()->getGroup()?->getId(),
            'user_id' => $user->getId(),
            'user_first_name' => $user->getFirstName(),
            'user_last_name' => $user->getLastName(),
            'user_email' => $user->getEmail(),
            'user_external_ids' => json_encode($user->getConsolidatedExternalLogins()),
            'locked_at' => $lock->getCreatedAt()->getTimestamp(),
            'unlocked_at' => $lock->getUnlockedAt()?->getTimestamp(),
            'remote_addr' => $lock->getRemoteAddr(),
            'exam_begin_at' => $lock->getGroupExam()->getBegin()->getTimestamp(),
            'exam_end_at' => $lock->getGroupExam()->getEnd()->getTimestamp(),
            'lock_strict' => $lock->getGroupExam()->isLockStrict(),
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

        $locks = $this->examLocks->findByCreatedAt($from, $to);

        foreach ($locks as $lock) {
            $data = $this->getLockData($lock);
            $this->printCsvLine($data);
        }

        return Command::SUCCESS;
    }
}
