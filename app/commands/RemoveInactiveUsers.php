<?php

namespace App\Console;

use App\Model\Repository\Users;
use App\Helpers\AnonymizationHelper;
use DateTime;
use DateInterval;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;

/**
 * A console command that removes inactive users.
 * A user is inactive if no authentication (nor token renewal) is recorded for given period of time.
 */
class RemoveInactiveUsers extends Command
{
    protected static $defaultName = 'users:remove-inactive';

    /** @var DateTime|null */
    private $disableThreshold = null;

    /** @var DateTime|null */
    private $deleteThreshold = null;

    /** @var string[] */
    private $roles = [];

    /** @var Users */
    private $users;

    /** @var AnonymizationHelper */
    public $anonymizationHelper;

    /**
     * Compute threshold date time from given interval description.
     * @param string|null $sub (e.g., "1 year")
     * @param DateTime|null $safe lower bound for a threshold (older of safe/result dates is returned)
     * @return DateTime|null
     */
    private static function computeThreshold(?string $sub, ?DateTime $safe = null): ?DateTime
    {
        if (!$sub) {
            return null;
        }
        $res = new DateTime();
        $res->sub(DateInterval::createFromDateString($sub));
        return ($safe && $safe < $res) ? $safe : $res;
    }

    public function __construct(array $config, Users $users, AnonymizationHelper $anonymizationHelper)
    {
        parent::__construct();
        $safeguard = self::computeThreshold('1 month'); // safe guard (so we do not delete/disable too new users)
        $this->disableThreshold = self::computeThreshold($config["disableAfter"] ?? null, $safeguard);
        $this->deleteThreshold = self::computeThreshold($config["deleteAfter"] ?? null, $safeguard);
        $this->roles = $config["roles"] ?? [];

        $this->users = $users;
        $this->anonymizationHelper = $anonymizationHelper;
    }

    protected function configure()
    {
        $this->setName('users:remove-inactive')->setDescription('Remove users who has not been active for some time.');
        $this->addOption(
            'report',
            null,
            InputOption::VALUE_NONE,
            'Just report, how many users would be disabled or deleted.'
        );
        $this->addOption('silent', null, InputOption::VALUE_NONE, 'Just do the job (no interactions, no reports)');
        $this->addOption(
            'really-delete',
            null,
            InputOption::VALUE_NONE,
            'Deletion must be explicitly enabled by this option.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $report = $input->getOption('report');
        $silent = !$report && $input->getOption('silent');
        $reallyDelete = $report || $input->getOption('really-delete');

        if (!$silent) {
            if ($this->roles) {
                $output->writeln("The cleanup aims only at users with the following roles: "
                    . join(', ', $this->roles));
            }
            if ($this->disableThreshold) {
                $output->writeln("Disabling users not authenticated since: "
                    . $this->disableThreshold->format("j.n.Y (H:i:s)"));
            }
            if ($this->deleteThreshold && $reallyDelete) {
                $output->writeln("Deleting disabled users not authenticated since: "
                    . $this->deleteThreshold->format("j.n.Y (H:i:s)"));
            }
        }

        // get users for disabling
        $usersToDisable = $this->disableThreshold
            ? $this->users->findByLastAuthentication($this->disableThreshold, true, $this->roles) : [];
        $usersToDelete = ($this->deleteThreshold && $reallyDelete)
            ? $this->users->findByLastAuthentication($this->deleteThreshold, false, $this->roles) : [];

        $disableCount = count($usersToDisable);
        $deleteCount = count($usersToDelete);

        if ($report) {
            $output->writeln("User that would have been disabled: $disableCount");
            $output->writeln("User that would have been deleted: $deleteCount");
            return Command::SUCCESS;
        }

        if (!$usersToDisable && !$usersToDelete) {
            if (!$silent) {
                $output->writeln("No inactive users found.");
            }
            return Command::SUCCESS;
        }

        // confirm the operation (when in interactive mode)
        if (!$silent) {
            $questionText = [];
            if ($disableCount) {
                $questionText[] = "Total $disableCount inactive users will be disabled.";
            }
            if ($deleteCount) {
                $questionText[] = "Total $deleteCount inactive disabled users will be deleted.";
            }
            $questionText[] = "Do you wish to proceed? ";

            /** @var QuestionHelper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(join(' ', $questionText), false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("Aborted.");
                return Command::SUCCESS;
            }
        }

        // disable users
        foreach ($usersToDisable as $user) {
            $user->setIsAllowed(false);
            $this->users->persist($user, false);
        }

        // delete users
        foreach ($usersToDelete as $user) {
            $this->anonymizationHelper->prepareUserForSoftDelete($user);
            $this->users->remove($user, false);
        }
        $this->users->flush();

        if (!$silent) {
            $output->writeln("All $disableCount users was disabled and $deleteCount users was deleted.");
        }

        return Command::SUCCESS;
    }
}
