<?php

namespace App\Console;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Helpers\AnonymizationHelper;
use DateTime;
use DateInterval;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * A console command that removes inactive users.
 * A user is inactive if no authentication (nor token renewal) is recorded for given period of time.
 */
class RemoveInactiveUsers extends Command {
  /** @var string */
  private $inactivityThreshold;

  /** @var Users */
  private $users;

  /** @var AnonymizationHelper */
  public $anonymizationHelper;

  public function __construct(?string $inactivityThreshold, Users $users, AnonymizationHelper $anonymizationHelper) {
    parent::__construct();
    $this->inactivityThreshold = $inactivityThreshold;
    $this->users = $users;
    $this->anonymizationHelper = $anonymizationHelper;
  }

  protected function configure() {
    $this->setName('users:remove-inactive')->setDescription('Remove users who has not been active for some time.');
    $this->addOption('silent', null, InputOption::VALUE_NONE, 'Just do the job (no interactions, no reports)');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if (!$this->inactivityThreshold) return;
    $silent = $input->getOption('silent');

    $before = new DateTime();
    $before->sub(DateInterval::createFromDateString($this->inactivityThreshold));
    $beforeSafe = new DateTime(); // safe guard (so we do not delete all users)
    $beforeSafe->sub(DateInterval::createFromDateString('1 month'));

    // get users for deletion
    $usersToDelete = $this->users->findByLastAuthentication($before < $beforeSafe ? $before : $beforeSafe);
    $count = count($usersToDelete);
    if ($count === 0) {
      if (!$silent) {
        $output->writeln("No inactive users found.");
      }
      return;
    }

    // confirm the deletion (when in interactive mode)
    if (!$silent) {
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion("Total $count inactive users found. Do you wish to remove them? ", false);
      if (!$helper->ask($input, $output, $question)) {
        $output->writeln("Aborted.");
        return;
      }
    }

    // perform deletion
    foreach ($usersToDelete as $user) {
      $this->anonymizationHelper->prepareUserForSoftDelete($user);
      $this->users->remove($user);
    }
    $this->users->flush();

    if (!$silent) {
      $output->writeln("All $count users have been removed.");
    }
  }
}
