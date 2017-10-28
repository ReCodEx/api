<?php
namespace App\Console;

use App\Helpers\UploadsConfig;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\LocalizedExercise;
use App\Model\Repository\Assignments;
use App\Model\Repository\Exercises;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupLocalizedTexts extends Command {

  /** @var Exercises */
  private $exercises;

  /** @var Assignments */
  private $assignments;

  /** @var EntityManager */
  private $entityManager;

  public function __construct(Exercises $exercises, Assignments $assignments, EntityManager $entityManager) {
    parent::__construct();
    $this->exercises = $exercises;
    $this->assignments = $assignments;
    $this->entityManager = $entityManager;
  }

  protected function configure() {
    $this->setName('localized-texts:cleanup')->setDescription('Remove unused localized texts.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $now = new DateTime();
    $deleted = 0;

    /** @var string[] $usedTexts */
    $usedTexts = [];

    /** @var Exercise $exercise */
    foreach ($this->exercises->findAll() as $exercise) {
      foreach ($exercise->getLocalizedTexts() as $localizedText) {
        $usedTexts[] = $localizedText->getId();
      }
    }

    /** @var Assignment $assignment */
    foreach ($this->assignments->findAll() as $assignment) {
      foreach ($assignment->getLocalizedTexts() as $localizedText) {
        $usedTexts[] = $localizedText->getId();
      }
    }

    $deleteQuery = $this->entityManager->createQuery('
      DELETE FROM App\Model\Entity\LocalizedExercise l
      WHERE l.id NOT IN (:ids)
    ');

    $limit = clone $now;
    $limit->modify("-1 day");
    // $deleteQuery->setParameter(":date", $limit);

    $deleteQuery->setParameter("ids", $usedTexts, Connection::PARAM_STR_ARRAY);

    $deleted += $deleteQuery->execute();

    $output->writeln(sprintf("Removed %d unused entities", $deleted));
    return 0;
  }
}
