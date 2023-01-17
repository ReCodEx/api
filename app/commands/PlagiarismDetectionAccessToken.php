<?php

namespace App\Console;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Security\AccessManager;
use App\Security\TokenScope;
use App\Security\Roles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Exception;

/**
 * A console command that removes inactive users.
 * A user is inactive if no authentication (nor token renewal) is recorded for given period of time.
 */
class PlagiarismDetectionAccessToken extends Command
{
    protected static $defaultName = 'plagiarism:create-access-token';

    /** @var AccessManager */
    public $accessManager;

    /** @var Users */
    private $users;

    public function __construct(AccessManager $accessManager, Users $users)
    {
        parent::__construct();
        $this->accessManager = $accessManager;
        $this->users = $users;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)
            ->setDescription('Generate token restricted for plagiarsim scope (for 3rd party tools).');
        $this->addArgument('userId', InputArgument::REQUIRED, 'ID of the admin owning the token.');
        $this->addOption(
            'expiration',
            null,
            InputOption::VALUE_REQUIRED,
            'Expiration time in seconds (default is a day).',
            86400
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get user by given ID
        $userId = $input->getArgument('userId');
        try {
            $user = $this->users->get($userId);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }

        // the user must exist and must have super admin role
        if (!$user) {
            $output->writeln("User $userId not found.");
            return Command::FAILURE;
        }

        if ($user->getRole() != Roles::SUPERADMIN_ROLE) {
            $output->writeln("User $userId does not have a super admin role.");
            return Command::FAILURE;
        }

        // get the expiration time
        $expiration = (int)$input->getOption('expiration');
        if ($expiration < 60 || $expiration > 365 * 86400) {
            $output->writeln("The expiration time must be in 1 minute - 1 year range");
            return Command::FAILURE;
        }

        $token = $this->accessManager->issueToken(
            $user,
            null,
            [TokenScope::PLAGIARISM, TokenScope::REFRESH],
            $expiration
        );

        $output->writeln($token);
        return Command::SUCCESS;
    }
}
