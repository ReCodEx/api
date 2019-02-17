<?php

namespace App\Console;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Helpers\AnonymizationHelper;
use DateTime;
use Exception;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

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
    $this->addOption('no-interaction', null, InputOption::VALUE_NONE, 'Just do the job (no interactions, no reports)');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if (!$this->inactivityThreshold) return;

    $before = new DateTime();
    $before->sub(DateInterval::createFromDateString($this->inactivityThreshold));
    $beforeSafe = new DateTime(); // safe guard (so we do not delete all users)
    $beforeSafe->sub(DateInterval::createFromDateString('1 month'));

    $usersToDelete = $this->users->findByLastAuthentication($before < $beforeSafe ? $before : $beforeSafe);
    foreach ($usersToDelete as $user) {
      $this->anonymizationHelper->prepareUserForSoftDelete($user);
      $this->users->remove($user);
    }
    $this->users->flush();
  }
}
